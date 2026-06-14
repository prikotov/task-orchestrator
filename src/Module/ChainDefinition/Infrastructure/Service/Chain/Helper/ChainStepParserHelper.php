<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\Helper;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionExpressionVo;

/**
 * Парсер шагов цепочки из raw YAML-массива в {@see ChainStepVo}.
 *
 * Вынесен из {@see \TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\YamlChainLoaderService}
 * как чистая, не имеющая состояния утилита парсинга DSL-шагов (без I/O).
 * Детерминирован: одинаковый вход всегда даёт одинаковый результат или одно и то же исключение.
 */
final class ChainStepParserHelper
{
    /**
     * Парсит список шагов цепочки (agent, quality_gate, tool).
     *
     * @param list<array<string, mixed>> $stepsData
     * @param ChainRetryPolicyVo|null $chainRetryPolicy политика retry уровня цепочки (fallback для шагов без своей)
     * @param bool $chainNoContextFiles флаг no_context_files уровня цепочки (fallback для шагов без своего)
     *
     * @return list<ChainStepVo>
     *
     * @throws InvalidArgumentException если шаг некорректен (отсутствует type/role/command/label и т.п.)
     */
    public static function parseSteps(
        string $name,
        array $stepsData,
        ?ChainRetryPolicyVo $chainRetryPolicy,
        bool $chainNoContextFiles,
    ): array {
        return array_map(
            static fn (array $step): ChainStepVo => self::buildStep(
                $name,
                $step,
                $chainRetryPolicy,
                $chainNoContextFiles,
            ),
            $stepsData,
        );
    }

    /**
     * Парсит retry_policy из YAML-конфигурации.
     *
     * @param array{max_retries?: int, initial_delay_ms?: int, max_delay_ms?: int, multiplier?: float}|null $raw
     */
    public static function parseRetryPolicy(?array $raw): ?ChainRetryPolicyVo
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        return ChainRetryPolicyVo::createFromArray($raw);
    }

    /**
     * Определяет тип шага и делегирует построение соответствующему билдеру.
     *
     * @param array<string, mixed> $step
     *
     * @throws InvalidArgumentException если type отсутствует или неизвестен
     */
    private static function buildStep(
        string $name,
        array $step,
        ?ChainRetryPolicyVo $chainRetryPolicy,
        bool $chainNoContextFiles,
    ): ChainStepVo {
        $stepType = ChainStepTypeEnum::tryFrom($step['type'] ?? '') ?? throw new InvalidArgumentException(
            sprintf('Step "type" is required in chain "%s" (expected: agent, quality_gate or tool).', $name),
        );

        $when = self::parseWhen($step);
        $postStep = self::parsePostStep($step);

        return match (true) {
            $stepType === ChainStepTypeEnum::tool => self::buildToolStep($name, $step, $when, $postStep),
            $stepType === ChainStepTypeEnum::qualityGate => self::buildQualityGateStep($name, $step, $when, $postStep),
            default => self::buildAgentStep($name, $step, $chainRetryPolicy, $chainNoContextFiles, $when, $postStep),
        };
    }

    /**
     * Строит tool-шаг (внешняя команда, не AI-агент).
     *
     * @param array<string, mixed> $step
     *
     * @throws InvalidArgumentException если отсутствуют command или label
     */
    private static function buildToolStep(string $name, array $step, ?ConditionExpressionVo $when, ?string $postStep): ChainStepVo
    {
        $command = $step['command'] ?? null;
        $label = $step['label'] ?? null;

        if ($command === null || $command === '') {
            throw new InvalidArgumentException(
                sprintf('Tool step must have "command" in chain "%s".', $name),
            );
        }

        if ($label === null || $label === '') {
            throw new InvalidArgumentException(
                sprintf('Tool step must have "label" in chain "%s".', $name),
            );
        }

        return ChainStepVo::createTool(
            command: $command,
            label: $label,
            timeoutSeconds: $step['timeout_seconds'] ?? 120,
            outputKey: $step['output_key'] ?? null,
            name: $step['name'] ?? null,
            when: $when,
            postStep: $postStep,
        );
    }

    /**
     * Строит quality_gate-шаг (проверка качества: lint, tests и т.п.).
     *
     * @param array<string, mixed> $step
     *
     * @throws InvalidArgumentException если отсутствуют command или label
     */
    private static function buildQualityGateStep(string $name, array $step, ?ConditionExpressionVo $when, ?string $postStep): ChainStepVo
    {
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

        return ChainStepVo::createQualityGate(
            command: $command,
            label: $label,
            timeoutSeconds: $step['timeout_seconds'] ?? 120,
            name: $step['name'] ?? null,
            when: $when,
            postStep: $postStep,
        );
    }

    /**
     * Строит agent-шаг (вызов AI-агента с указанной ролью).
     *
     * @param array<string, mixed> $step
     *
     * @throws InvalidArgumentException если отсутствует role
     */
    private static function buildAgentStep(
        string $name,
        array $step,
        ?ChainRetryPolicyVo $chainRetryPolicy,
        bool $chainNoContextFiles,
        ?ConditionExpressionVo $when,
        ?string $postStep,
    ): ChainStepVo {
        $stepRetryPolicy = self::parseRetryPolicy($step['retry_policy'] ?? null);
        $stepNoContextFiles = (bool) ($step['no_context_files'] ?? $chainNoContextFiles);

        return ChainStepVo::createAgent(
            role: $step['role'] ?? throw new InvalidArgumentException(
                sprintf('Agent step "role" is required in chain "%s".', $name),
            ),
            runner: $step['runner'] ?? 'pi',
            tools: $step['tools'] ?? null,
            model: $step['model'] ?? null,
            retryPolicy: $stepRetryPolicy ?? $chainRetryPolicy,
            name: $step['name'] ?? null,
            noContextFiles: $stepNoContextFiles,
            when: $when,
            postStep: $postStep,
            runnerExplicit: array_key_exists('runner', $step),
        );
    }

    /**
     * Парсит опциональное when-выражение (условное ветвление шага).
     *
     * @param array<string, mixed> $step
     */
    private static function parseWhen(array $step): ?ConditionExpressionVo
    {
        if (!isset($step['when']) || !is_string($step['when']) || $step['when'] === '') {
            return null;
        }

        return ConditionExpressionVo::createFromExpression($step['when']);
    }

    /**
     * Парсит опциональный post_step hook (имя hook-сервиса, вызываемого после шага).
     *
     * @param array<string, mixed> $step
     */
    private static function parsePostStep(array $step): ?string
    {
        if (!isset($step['post_step']) || !is_string($step['post_step']) || $step['post_step'] === '') {
            return null;
        }

        return $step['post_step'];
    }
}
