<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\Entity\StaticChainExecution;
use TasK\Orchestrator\Domain\ValueObject\BudgetVo;

/**
 * Проверка бюджетных ограничений static-цепочки.
 */
interface CheckStaticBudgetServiceInterface
{
    /**
     * Проверяет бюджет перед выполнением шага.
     *
     * @return bool true — нужно прервать цепочку
     */
    public function shouldBreakBeforeStep(
        StaticChainExecution $execution,
        ?BudgetVo $budget,
        string $budgetRole,
    ): bool;

    /**
     * Проверяет бюджет после выполнения шага.
     *
     * @return bool true — нужно прервать цепочку
     */
    public function shouldBreakAfterStep(
        StaticChainExecution $execution,
        ?BudgetVo $budget,
        string $budgetRole,
        float $stepCost,
    ): bool;
}
