<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * Результат обработки одного шага static-цепочки (Domain VO).
 *
 * Заменяет StepProcessResultDto. Управляет потоком выполнения цикла:
 * shouldRetry — повторить итерацию, shouldBreak — прервать цепочку.
 *
 * @psalm-type StaticStepResults = list<StaticStepResultVo>
 */
final readonly class StaticProcessResultVo
{
    /**
     * @param list<StaticStepResultVo> $results
     */
    public function __construct(
        public array $results,
        public bool $shouldRetry,
        public bool $shouldBreak = false,
    ) {
    }
}
