<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Результат обработки одного шага static-цепочки (Domain VO).
 *
 * Заменяет StepProcessResultDto. Управляет потоком выполнения цикла:
 * isRetryNeeded — повторить итерацию, isBreakNeeded — прервать цепочку.
 *
 * @psalm-type StaticStepResults = list<StaticStepResultVo>
 */
final readonly class StaticProcessResultVo
{
    /**
     * @param list<StaticStepResultVo> $results
     */
    public function __construct(
        private array $results,
        private bool $shouldRetry,
        private bool $shouldBreak = false,
    ) {
    }

    /**
     * @return list<StaticStepResultVo>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Нужно ли повторить итерацию шага.
     */
    public function isRetryNeeded(): bool
    {
        return $this->shouldRetry;
    }

    /**
     * Нужно ли прервать выполнение цепочки.
     */
    public function isBreakNeeded(): bool
    {
        return $this->shouldBreak;
    }
}
