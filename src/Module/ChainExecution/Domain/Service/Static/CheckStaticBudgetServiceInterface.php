<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\Entity\StaticChainExecution;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionBudgetVo;

/**
 * Проверка бюджетных ограничений static-цепочки.
 */
interface CheckStaticBudgetServiceInterface
{
    /**
     * Проверяет бюджет перед выполнением шага.
     */
    public function shouldBreakBeforeStep(
        StaticChainExecution $execution,
        ?ExecutionBudgetVo $budget,
        string $budgetRole,
    ): bool;

    /**
     * Проверяет бюджет после выполнения шага.
     */
    public function shouldBreakAfterStep(
        StaticChainExecution $execution,
        ?ExecutionBudgetVo $budget,
        string $budgetRole,
        float $stepCost,
    ): bool;
}
