<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicBudgetCheckVo;

/**
 * Проверка и контроль бюджета dynamic-цикла.
 */
interface CheckDynamicLoopBudgetServiceInterface
{
    /**
     * Проверяет бюджет и применяет результат к execution entity.
     */
    public function checkAndApply(
        DynamicLoopExecution $execution,
        ?BudgetVo $budget,
        string $role,
        float $stepCost,
    ): ?DynamicBudgetCheckVo;
}
