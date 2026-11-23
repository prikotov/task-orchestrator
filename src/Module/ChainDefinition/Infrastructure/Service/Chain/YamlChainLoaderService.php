<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain;

use InvalidArgumentException;
use Override;
use Symfony\Component\Yaml\Yaml;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\ChainNotFoundException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ResolveYamlDynamicPromptsServiceInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\YamlChainLoaderServiceInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlChainStepMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlRetryPolicyMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlRoleConfigMapper;

/**
 * Реализация ChainLoaderInterface — загрузка цепочек из YAML-файла.
 *
 * Поддерживает три типа цепочек:
 * - static (по умолчанию): фиксированные шаги
 * - dynamic: фасилитатор + участники
 * - conditional: шаги с условным ветвлением (when-expressions).
 *
 * Технические подзадачи делегированы узким сотрудникам того же модуля:
 * - преобразование секции `roles` (command_profile/commands_by_complexity/fallback)
 *   — {@see YamlRoleConfigMapper};
 * - загрузка prompt-конфигурации dynamic-цепочек (файл/инлайн, I/O) —
 *   {@see ResolveYamlDynamicPromptsServiceInterface}.
 *
 * Также парсит секции `roles` (per-role конфигурация: command, timeout,
 * prompt_file, fallback) и `retry_policy` на уровне цепочки и шага.
 */
final class YamlChainLoaderService implements YamlChainLoaderServiceInterface
{
    private string $yamlPath;

    /** @var array<string, ChainDefinitionInterface>|null */
    private ?array $chains = null;

    public function __construct(
        string $yamlPath,
        private readonly ChainDefinitionFactory $chainDefinitionFactory,
        private readonly YamlChainStepMapper $yamlChainStepMapper,
        private readonly YamlRetryPolicyMapper $yamlRetryPolicyMapper,
        private readonly YamlRoleConfigMapper $yamlRoleConfigMapper,
        private readonly ResolveYamlDynamicPromptsServiceInterface $promptsResolver,
    ) {
        $this->yamlPath = $yamlPath;
    }

    /**
     * Переопределяет путь к YAML-файлу и сбрасывает кэш.
     *
     * Используется CLI-опцией --config для загрузки произвольного файла chains.yaml
     * без изменения Symfony-конфигурации.
     */
    #[Override]
    public function overridePath(string $yamlPath): void
    {
        $this->yamlPath = $yamlPath;
        $this->chains = null;
    }

    #[Override]
    public function load(string $name): ChainDefinitionInterface
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
     * @return array<string, ChainDefinitionInterface>
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
        $roles = $this->yamlRoleConfigMapper->mapToRoles(
            raw: $yaml['roles'] ?? [],
            profiles: $yaml['command_profiles'] ?? [],
        );

        foreach ($rawChains as $name => $raw) {
            $this->chains[$name] = $this->parseChainDefinition($name, $raw, $roles);
        }

        return $this->chains;
    }

    /**
     * Маппит raw-массив из YAML в специализированный ChainDefinitionInterface VO.
     *
     * @param array<string, mixed> $raw
     * @param array<string, RoleConfigVo> $roles
     */
    private function parseChainDefinition(string $name, array $raw, array $roles): ChainDefinitionInterface
    {
        $type = ChainTypeEnum::tryFrom($raw['type'] ?? 'static') ?? ChainTypeEnum::staticType;

        return match (true) {
            $type === ChainTypeEnum::dynamicType => $this->parseDynamicChain($name, $raw, $roles),
            $type === ChainTypeEnum::conditionalType => $this->parseConditionalChain($name, $raw, $roles),
            default => $this->parseStaticChain($name, $raw, $roles),
        };
    }

    /**
     * Парсит static-цепочку (обратная совместимость).
     *
     * @param array<string, mixed> $raw
     * @param array<string, RoleConfigVo> $roles
     */
    private function parseStaticChain(string $name, array $raw, array $roles): ChainDefinitionInterface
    {
        $stepsData = $raw['steps'] ?? [];
        $chainRetryPolicy = $this->yamlRetryPolicyMapper->mapToChainRetryPolicy($raw['retry_policy'] ?? null);
        $budget = $this->parseBudget($raw['budget'] ?? null);
        $chainNoContextFiles = (bool) ($raw['no_context_files'] ?? false);

        $steps = $this->yamlChainStepMapper->mapToChainSteps($name, $stepsData, $chainRetryPolicy, $chainNoContextFiles);
        $fixIterations = $this->parseFixIterations($raw['fix_iterations'] ?? []);

        // Auto-detect conditional: если хотя бы один шаг имеет when-выражение
        $hasConditions = false;
        foreach ($steps as $step) {
            if ($step->hasCondition()) {
                $hasConditions = true;
                break;
            }
        }

        if ($hasConditions) {
            $this->validateWhenReferences($name, $steps);

            return $this->chainDefinitionFactory->createFromConditionalSteps(
                name: $name,
                description: $raw['description'] ?? '',
                steps: $steps,
                fixIterations: $fixIterations,
                roles: $roles,
                defaultRetryPolicy: $chainRetryPolicy,
                budget: $budget,
                timeout: $raw['timeout'] ?? null,
            );
        }

        return $this->chainDefinitionFactory->createFromSteps(
            name: $name,
            description: $raw['description'] ?? '',
            steps: $steps,
            fixIterations: $fixIterations,
            roles: $roles,
            defaultRetryPolicy: $chainRetryPolicy,
            budget: $budget,
            timeout: $raw['timeout'] ?? null,
        );
    }

    /**
     * Парсит conditional-цепочку (type: conditional в YAML).
     *
     * То же самое что static, но тип всегда conditional (явное указание).
     *
     * @param array<string, mixed> $raw
     * @param array<string, RoleConfigVo> $roles
     */
    private function parseConditionalChain(string $name, array $raw, array $roles): ChainDefinitionInterface
    {
        $stepsData = $raw['steps'] ?? [];
        $chainRetryPolicy = $this->yamlRetryPolicyMapper->mapToChainRetryPolicy($raw['retry_policy'] ?? null);
        $budget = $this->parseBudget($raw['budget'] ?? null);
        $chainNoContextFiles = (bool) ($raw['no_context_files'] ?? false);

        $steps = $this->yamlChainStepMapper->mapToChainSteps($name, $stepsData, $chainRetryPolicy, $chainNoContextFiles);
        $fixIterations = $this->parseFixIterations($raw['fix_iterations'] ?? []);

        $this->validateWhenReferences($name, $steps);

        return $this->chainDefinitionFactory->createFromConditionalSteps(
            name: $name,
            description: $raw['description'] ?? '',
            steps: $steps,
            fixIterations: $fixIterations,
            roles: $roles,
            defaultRetryPolicy: $chainRetryPolicy,
            budget: $budget,
            timeout: $raw['timeout'] ?? null,
        );
    }

    /**
     * Валидирует when-выражения: path references (steps.<name>.*) должны ссылаться на существующие именованные шаги.
     *
     * @param list<ChainStepVo> $steps
     *
     * @throws InvalidArgumentException если when-ссылка указывает на несуществующий шаг
     */
    private function validateWhenReferences(string $name, array $steps): void
    {
        // Собираем map name → index
        $nameMap = [];
        foreach ($steps as $index => $step) {
            $stepName = $step->getName();
            if ($stepName !== null) {
                $nameMap[$stepName] = $index;
            }
        }

        // Проверяем when-ссылки
        foreach ($steps as $index => $step) {
            $when = $step->getWhen();
            if ($when === null) {
                continue;
            }

            if (!$when->referencesStep()) {
                continue;
            }

            $referencedName = $when->getReferencedStepName();
            if ($referencedName === null) {
                throw new InvalidArgumentException(sprintf(
                    'Chain "%s": step %d has invalid when-reference "%s".',
                    $name,
                    $index,
                    $when->getPath(),
                ));
            }

            if (!isset($nameMap[$referencedName])) {
                throw new InvalidArgumentException(sprintf(
                    'Chain "%s": step %d when-condition references unknown step name "%s".',
                    $name,
                    $index,
                    $referencedName,
                ));
            }

            // Запрещаем forward-ссылки: шаг может ссылаться только на предыдущие шаги
            if ($nameMap[$referencedName] >= $index) {
                throw new InvalidArgumentException(sprintf(
                    'Chain "%s": step %d when-condition references step "%s" which is not yet executed (index %d >= %d).',
                    $name,
                    $index,
                    $referencedName,
                    $nameMap[$referencedName],
                    $index,
                ));
            }
        }
    }

    /**
     * Парсит dynamic-цепочку.
     *
     * @param array<string, mixed> $raw
     * @param array<string, RoleConfigVo> $roles
     */
    private function parseDynamicChain(string $name, array $raw, array $roles): ChainDefinitionInterface
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

        $prompts = $this->promptsResolver->resolve(
            chainName: $name,
            yamlPath: $this->yamlPath,
            rawPrompts: $raw['prompts'] ?? [],
        );
        $budget = $this->parseBudget($raw['budget'] ?? null);
        $chainRetryPolicy = $this->yamlRetryPolicyMapper->mapToChainRetryPolicy($raw['retry_policy'] ?? null);

        return $this->chainDefinitionFactory->createFromDynamic(
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
            defaultRetryPolicy: $chainRetryPolicy,
            budget: $budget,
            timeout: $raw['timeout'] ?? null,
            maxTime: $raw['max_time'] ?? null,
        );
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

        return BudgetVo::createFromArray($raw);
    }
}
