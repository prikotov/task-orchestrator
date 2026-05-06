<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain;

use TaskOrchestrator\Common\Module\ChainExecution\Application\Enum\OrchestrateExitCodeEnum;

/**
 * DTO результата оркестрации цепочки.
 *
 * Содержит результаты всех шагов/раундов и агрегированные метрики.
 * Поддерживает как static, так и dynamic цепочки.
 */
final readonly class OrchestrateChainResultDto
{
    /**
     * @param list<StepResultDto> $stepResults результаты шагов (static цепочки)
     * @param list<DynamicRoundResultDto> $roundResults результаты раундов (dynamic цепочки)
     * @param string|null $synthesis итоговый synthesis от фасилитатора (dynamic)
     * @param bool $maxRoundsReached достигнут ли лимит раундов (dynamic)
     */
    public function __construct(
        public array $stepResults = [],
        public array $roundResults = [],
        public float $totalTime = 0.0,
        public int $totalInputTokens = 0,
        public int $totalOutputTokens = 0,
        public float $totalCost = 0.0,
        public ?string $synthesis = null,
        public bool $maxRoundsReached = false,
        public ?string $sessionDir = null,
        public bool $budgetExceeded = false,
        public float $budgetLimit = 0.0,
        public ?string $budgetExceededRole = null,
        public int $totalIterations = 0,
        public bool $timedOut = false,
    ) {
    }

    /**
     * Определяет exit code по результату оркестрации.
     */
    public function resolveExitCode(bool $isDynamic): OrchestrateExitCodeEnum
    {
        if ($this->budgetExceeded) {
            return OrchestrateExitCodeEnum::budgetExceeded;
        }

        if ($this->timedOut) {
            return OrchestrateExitCodeEnum::timeout;
        }

        if ($isDynamic) {
            return $this->synthesis !== null
                ? OrchestrateExitCodeEnum::success
                : OrchestrateExitCodeEnum::chainFailed;
        }

        return $this->staticChainHasError()
            ? OrchestrateExitCodeEnum::chainFailed
            : OrchestrateExitCodeEnum::success;
    }

    /**
     * Проверяет, завершена ли цепочка успешно (для рендера итогового сообщения).
     */
    public function isSuccessful(bool $isDynamic): bool
    {
        return $this->resolveExitCode($isDynamic) === OrchestrateExitCodeEnum::success;
    }

    /**
     * Проверяет, содержит ли static-цепочка ошибку на каком-либо шаге.
     */
    private function staticChainHasError(): bool
    {
        foreach ($this->stepResults as $stepResult) {
            if ($stepResult->isError) {
                return true;
            }
        }

        return false;
    }
}
