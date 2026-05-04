<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicBudgetCheckVo;

/**
 * Проверка и контроль бюджета dynamic-цикла.
 */
interface CheckDynamicLoopBudgetServiceInterface
{
    public function checkAndApply(
        DynamicLoopExecution $execution,
        ?DynamicLoopBudgetVo $budget,
        string $role,
        float $stepCost,
    ): ?DynamicBudgetCheckVo;
}
