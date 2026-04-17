<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\ChainNotFoundException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FallbackConfigVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FixIterationGroupVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RoleConfigVo;
use InvalidArgumentException;
use Override;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Реализация ChainLoaderInterface — загрузка цепочек из YAML-файла.
 *
 * Поддерживает два типа цепочек:
 * - static (по умолчанию): фиксированные шаги
 * - dynamic: фасилитатор + участники.
 *
 * Также парсит секцию `roles` с per-role конфигурацией
 * (command, timeout, prompt_file, fallback) и `retry_policy` на уровне цепочки и шага.
 */
final class YamlChainLoader implements ChainLoaderInterface
{
    private string $yamlPath;

    /** @var array<string, ChainDefinitionVo>|null */
    private ?array $chains = null;

    public function __construct(string $yamlPath)
    {
        $this->yamlPath = $yamlPath;
    }

    #[Override]
    public function load(string $name): ChainDefinitionVo
    {
        $chains = $this->loadAll();

        if (!isset($chains[$name])) {
            throw new ChainNotFoundException($name);
        }

        return $chains[$name];
    }

    #[Override]
    public function list(): array
    {
        return $this->loadAll();
    }

    /**
     * Загружает и кэширует цепочки из YAML.
     *
     * @return array<string, ChainDefinitionVo>
     */
    private function loadAll(): array
    {
        if ($this->chains !== null) {
            return $this->chains;
        }

        $this->chains = [];

        if (!file_exists($this->yamlPath)) {
            return $this->chains;
        }

        $yaml = Yaml::parseFile($this->yamlPath);
        $rawChains = $yaml['chains'] ?? [];
        $roles = $this->parseRoles($yaml['roles'] ?? []);

        foreach ($rawChains as $name => $raw) {
            $this->chains[$name] = $this->parseChainDefinition($name, $raw, $roles);
        }

        return $this->chains;
    }

    /**
     * Маппит raw-массив из YAML в ChainDefinitionVo.
     *
     * @param array<string, RoleConfigVo> $roles
     */
    private function parseChainDefinition(string $name, array $raw, array $roles): ChainDefinitionVo
    {
        $type = ChainTypeEnum::tryFrom($raw['type'] ?? 'static') ?? ChainTypeEnum::staticType;

        return $type === ChainTypeEnum::dynamicType
            ? $this->parseDynamicChain($name, $raw, $roles)
            : $this->parseStaticChain($name, $raw, $roles);
    }

    /**
     * Парсит static-цепочку (обратная совместимость).
     *
     * @param array<string, RoleConfigVo> $roles
     */
    private function parseStaticChain(string $name, array $raw, array $roles): ChainDefinitionVo
    {
        $stepsData = $raw['steps'] ?? [];
        $chainRetryPolicy = $this->parseRetryPolicy($raw['retry_policy'] ?? null);
        $budget = $this->parseBudget($raw['budget'] ?? null);

        $steps = array_values(array_map(
            function (array $step) use ($name, $chainRetryPolicy): ChainStepVo {
                $stepType = ChainStepTypeEnum::tryFrom($step['type'] ?? '') ?? throw new InvalidArgumentException(
                    sprintf('Step "type" is required in chain "%s" (expected: agent or quality_gate).', $name),
                );

                if ($stepType === ChainStepTypeEnum::qualityGate) {
                    $command = $step['command'] ?? null;
                    $label = $step['label'] ?? null;

                    if ($command === null || $command === '') {
                        throw new InvalidArgumentException(
                            sprintf('quality_gate step must have "command" in chain "%s".', $name),
                        );
                    }

                    if ($label === null || $label === '') {
                        throw new InvalidArgumentException(
                            sprintf('quality_gate step must have "label" in chain "%s".', $name),
                        );
                    }

                    return ChainStepVo::qualityGate(
                        command: $command,
                        label: $label,
                        timeoutSeconds: $step['timeout_seconds'] ?? 120,
                        name: $step['name'] ?? null,
                    );
                }

                // Agent step
                $stepRetryPolicy = $this->parseRetryPolicy($step['retry_policy'] ?? null);

                return ChainStepVo::agent(
                    role: $step['role'] ?? throw new InvalidArgumentException(
                        sprintf('Agent step "role" is required in chain "%s".', $name),
                    ),
                    runner: $step['runner'] ?? 'pi',
                    tools: $step['tools'] ?? null,
                    model: $step['model'] ?? null,
                    retryPolicy: $stepRetryPolicy ?? $chainRetryPolicy,
                    name: $step['name'] ?? null,
                );
            },
            $stepsData,
        ));

        $fixIterations = $this->parseFixIterations($raw['fix_iterations'] ?? []);

        return ChainDefinitionVo::createFromSteps(
            name: $name,
            description: $raw['description'] ?? '',
            steps: $steps,
            fixIterations: $fixIterations,
            roles: $roles,
            defaultRetryPolicy: $chainRetryPolicy,
            budget: $budget,
        );
    }

    /**
     * Парсит dynamic-цепочку.
     *
     * @param array<string, RoleConfigVo> $roles
     */
    private function parseDynamicChain(string $name, array $raw, array $roles): ChainDefinitionVo
    {
        $participants = $raw['participants'] ?? [];
        if (count($participants) === 0) {
            throw new InvalidArgumentException(
                sprintf('Dynamic chain "%s" must have at least one participant.', $name),
            );
        }

        $facilitator = $raw['facilitator'] ?? null;
        if ($facilitator === null || $facilitator === '') {
            throw new InvalidArgumentException(
                sprintf('Dynamic chain "%s" must specify a facilitator role.', $name),
            );
        }

        $prompts = $this->resolvePrompts($name, $raw);
        $budget = $this->parseBudget($raw['budget'] ?? null);

        return ChainDefinitionVo::createFromDynamic(
            name: $name,
            description: $raw['description'] ?? '',
            facilitator: $facilitator,
            participants: $participants,
            maxRounds: $raw['max_rounds'] ?? 10,
            brainstormSystemPrompt: $prompts['brainstorm_system'],
            facilitatorAppendPrompt: $prompts['facilitator_append'],
            facilitatorStartPrompt: $prompts['facilitator_start'],
            facilitatorContinuePrompt: $prompts['facilitator_continue'],
            facilitatorFinalizePrompt: $prompts['facilitator_finalize'],
            participantAppendPrompt: $prompts['participant_append'],
            participantUserPrompt: $prompts['participant_user'],
            roles: $roles,
            budget: $budget,
        );
    }

    /**
     * Парсит retry_policy из YAML-конфигурации.
     *
     * @param array{max_retries?: int, initial_delay_ms?: int, max_delay_ms?: int, multiplier?: float}|null $raw
     */
    private function parseRetryPolicy(?array $raw): ?RetryPolicyVo
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        return RetryPolicyVo::fromArray($raw);
    }

    /**
     * Парсит секцию fix_iterations из YAML.
     *
     * @param list<array<string, mixed>> $raw
     *
     * @return list<FixIterationGroupVo>
     */
    private function parseFixIterations(array $raw): array
    {
        $result = [];

        foreach ($raw as $item) {
            $group = $item['group'] ?? null;
            $steps = $item['steps'] ?? [];
            $maxIterations = $item['max_iterations'] ?? 3;

            if ($group === null || $group === '') {
                throw new InvalidArgumentException('fix_iteration "group" is required.');
            }

            if (!is_array($steps) || count($steps) < 2) {
                throw new InvalidArgumentException(
                    sprintf('fix_iteration group "%s" must have at least 2 steps.', $group),
                );
            }

            $stepNames = array_values(array_map('strval', $steps));

            $result[] = new FixIterationGroupVo(
                group: $group,
                stepNames: $stepNames,
                maxIterations: $maxIterations,
            );
        }

        return $result;
    }

    /**
     * Парсит budget из YAML-конфигурации.
     *
     * @param array{max_cost_total?: float|int|null, max_cost_per_step?: float|int|null}|null $raw
     */
    private function parseBudget(?array $raw): ?BudgetVo
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        return BudgetVo::fromArray($raw);
    }

    /**
     * Парсит секцию `roles` из YAML.
     *
     * Каждый элемент: { command, timeout, prompt_file, fallback }.
     * Все поля опциональны.
     *
     * Формат fallback в YAML:
     *   fallback:
     *     command:
     *       - codex
     *       - --model
     *       - gpt-4o
     *
     * @param array<string, mixed> $raw
     *
     * @return array<string, RoleConfigVo>
     */
    private function parseRoles(array $raw): array
    {
        $roles = [];

        foreach ($raw as $roleName => $config) {
            if (!is_array($config)) {
                continue;
            }

            $roles[$roleName] = new RoleConfigVo(
                command: $config['command'] ?? [],
                timeout: $config['timeout'] ?? null,
                promptFile: $config['prompt_file'] ?? null,
                fallback: $this->parseFallbackConfig($config),
            );
        }

        return $roles;
    }

    /**
     * Парсит fallback-конфигурацию из секции роли.
     *
     * @param array<string, mixed> $roleConfig
     */
    private function parseFallbackConfig(array $roleConfig): ?FallbackConfigVo
    {
        $fallback = $roleConfig['fallback'] ?? null;

        if (!is_array($fallback)) {
            return null;
        }

        $command = $fallback['command'] ?? [];

        if (!is_array($command) || $command === []) {
            return null;
        }

        /** @var list<string> $commandList */
        $commandList = array_values(array_map('strval', $command));

        return new FallbackConfigVo(command: $commandList);
    }

    /**
     * Разрешает промпты для dynamic-цепочки.
     *
     * Каждый элемент prompts может быть:
     * - путь к файлу (относительно директории YAML) — если файл существует, содержимое читается;
     * - инлайн-текст — используется как есть.
     *
     * @param string $name имя цепочки
     * @param array{prompts?: array<string, string>} $raw
     *
     * @return array<string, string>
     */
    private function resolvePrompts(string $name, array $raw): array
    {
        $prompts = $raw['prompts'] ?? [];
        $requiredPrompts = ['brainstorm_system', 'facilitator_append', 'facilitator_start', 'facilitator_continue', 'facilitator_finalize', 'participant_append', 'participant_user'];
        $baseDir = dirname($this->yamlPath);

        $resolved = [];
        foreach ($requiredPrompts as $key) {
            if (!isset($prompts[$key]) || $prompts[$key] === '') {
                throw new InvalidArgumentException(
                    sprintf('Dynamic chain "%s" must specify prompts.%s.', $name, $key),
                );
            }

            $value = $prompts[$key];
            $filePath = $baseDir . '/' . $value;

            $resolved[$key] = file_exists($filePath)
                ? trim(self::readFile($filePath))
                : $value;
        }

        return $resolved;
    }

    /**
     * Читает содержимое файла с проверкой на ошибку чтения.
     */
    private static function readFile(string $path): string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read prompt file: %s', $path));
        }

        return $content;
    }
}
