<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainStepFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionExpressionVo;

/**
 * Маппер шагов цепочки из raw YAML-массива в {@see ChainStepVo}.
 *
 * Техническая граница Infrastructure ↔ Domain: знает YAML-DSL keys (`type`, `runner`,
 * `retry_policy`, `timeout_seconds`, `no_context_files`, ...) и преобразует внешний формат
 * в вызовы доменной {@see ChainStepFactory}. Бизнес-логику (defaults, инварианты,
 * правило приоритета «шаг перекрывает цепочку») фабрике не дублирует.
 *
 * Type-dispatch по {@see ChainStepTypeEnum} трактуется как mapping discriminator
 * внешнего DSL, а не как доменная стратегия.
 *
 * @see docs/conventions/core_patterns/mapper.md
 */
final readonly class YamlChainStepMapper
{
    public function __construct(
        private ChainStepFactory $chainStepFactory,
        private YamlRetryPolicyMapper $yamlRetryPolicyMapper,
    ) {
    }

    /**
     * Маппит список raw YAML-шагов цепочки в список {@see ChainStepVo}.
     *
     * @param string $chainName имя цепочки — пробрасывается в фабрику для guard-сообщений
     * @param list<array<string, mixed>> $stepsData raw YAML-шаги
     * @param ChainRetryPolicyVo|null $chainRetryPolicy retry-политика уровня цепочки (fallback)
     * @param bool $chainNoContextFiles флаг no_context_files уровня цепочки (fallback)
     *
     * @return list<ChainStepVo>
     *
     * @throws InvalidArgumentException если шаг некорректен (отсутствует/неизвестен type и т.п.)
     */
    public function mapToChainSteps(
        string $chainName,
        array $stepsData,
        ?ChainRetryPolicyVo $chainRetryPolicy,
        bool $chainNoContextFiles,
    ): array {
        return array_map(
            fn (array $step): ChainStepVo => $this->mapStep($chainName, $step, $chainRetryPolicy, $chainNoContextFiles),
            $stepsData,
        );
    }

    /**
     * Маппит один raw YAML-шаг: определяет тип и делегирует в соответствующий метод.
     *
     * @param array<string, mixed> $step
     *
     * @throws InvalidArgumentException если type отсутствует или неизвестен
     */
    private function mapStep(
        string $chainName,
        array $step,
        ?ChainRetryPolicyVo $chainRetryPolicy,
        bool $chainNoContextFiles,
    ): ChainStepVo {
        $stepType = ChainStepTypeEnum::tryFrom($step['type'] ?? '') ?? throw new InvalidArgumentException(
            sprintf('Step "type" is required in chain "%s" (expected: agent, quality_gate or tool).', $chainName),
        );

        $when = $this->mapWhen($step);
        $postStep = $this->mapPostStep($step);

        return match (true) {
            $stepType === ChainStepTypeEnum::tool => $this->mapToolStep($chainName, $step, $when, $postStep),
            $stepType === ChainStepTypeEnum::qualityGate => $this->mapQualityGateStep($chainName, $step, $when, $postStep),
            default => $this->mapAgentStep($chainName, $step, $chainRetryPolicy, $chainNoContextFiles, $when, $postStep),
        };
    }

    /**
     * @param array<string, mixed> $step
     */
    private function mapToolStep(string $chainName, array $step, ?ConditionExpressionVo $when, ?string $postStep): ChainStepVo
    {
        return $this->chainStepFactory->createTool(
            chainName: $chainName,
            command: $step['command'] ?? null,
            label: $step['label'] ?? null,
            timeoutSeconds: $step['timeout_seconds'] ?? null,
            outputKey: $step['output_key'] ?? null,
            name: $step['name'] ?? null,
            when: $when,
            postStep: $postStep,
        );
    }

    /**
     * @param array<string, mixed> $step
     */
    private function mapQualityGateStep(string $chainName, array $step, ?ConditionExpressionVo $when, ?string $postStep): ChainStepVo
    {
        return $this->chainStepFactory->createQualityGate(
            chainName: $chainName,
            command: $step['command'] ?? null,
            label: $step['label'] ?? null,
            timeoutSeconds: $step['timeout_seconds'] ?? null,
            name: $step['name'] ?? null,
            when: $when,
            postStep: $postStep,
        );
    }

    /**
     * @param array<string, mixed> $step
     */
    private function mapAgentStep(
        string $chainName,
        array $step,
        ?ChainRetryPolicyVo $chainRetryPolicy,
        bool $chainNoContextFiles,
        ?ConditionExpressionVo $when,
        ?string $postStep,
    ): ChainStepVo {
        $stepRetryPolicy = $this->yamlRetryPolicyMapper->mapToChainRetryPolicy(
            $this->extractRetryPolicy($step),
        );

        $stepNoContextFiles = $this->extractNoContextFiles($step);

        return $this->chainStepFactory->createAgent(
            chainName: $chainName,
            role: $step['role'] ?? '',
            runner: $step['runner'] ?? null,
            runnerExplicit: array_key_exists('runner', $step),
            tools: $step['tools'] ?? null,
            model: $step['model'] ?? null,
            stepRetryPolicy: $stepRetryPolicy,
            chainRetryPolicy: $chainRetryPolicy,
            name: $step['name'] ?? null,
            stepNoContextFiles: $stepNoContextFiles,
            chainNoContextFiles: $chainNoContextFiles,
            when: $when,
            postStep: $postStep,
        );
    }

    /**
     * Извлекает raw retry_policy из шага как массив (или null), сохраняя отличие от отсутствия.
     *
     * @param array<string, mixed> $step
     *
     * @return array{max_retries?: int, initial_delay_ms?: int, max_delay_ms?: int, multiplier?: float}|null
     */
    private function extractRetryPolicy(array $step): ?array
    {
        $raw = $step['retry_policy'] ?? null;

        if (!is_array($raw)) {
            return null;
        }

        return $raw;
    }

    /**
     * Извлекает опциональный no_context_files из шага: null если ключ отсутствует ИЛИ равен null,
     * иначе bool. Семантика null-value = absent консистентна с {@see extractRetryPolicy}
     * и восстанавливает поведение оригинала (`null ?? chain` → наследование цепочки).
     * `false` и `true` сохраняются как значения (отличаются от null).
     *
     * @param array<string, mixed> $step
     */
    private function extractNoContextFiles(array $step): ?bool
    {
        $raw = $step['no_context_files'] ?? null;

        if ($raw === null) {
            return null;
        }

        return (bool) $raw;
    }

    /**
     * Извлекает опциональное when-выражение (условное ветвление шага).
     *
     * @param array<string, mixed> $step
     */
    private function mapWhen(array $step): ?ConditionExpressionVo
    {
        if (!isset($step['when']) || !is_string($step['when']) || $step['when'] === '') {
            return null;
        }

        return ConditionExpressionVo::createFromExpression($step['when']);
    }

    /**
     * Извлекает опциональный post_step hook (имя hook-сервиса).
     *
     * @param array<string, mixed> $step
     */
    private function mapPostStep(array $step): ?string
    {
        if (!isset($step['post_step']) || !is_string($step['post_step']) || $step['post_step'] === '') {
            return null;
        }

        return $step['post_step'];
    }
}
