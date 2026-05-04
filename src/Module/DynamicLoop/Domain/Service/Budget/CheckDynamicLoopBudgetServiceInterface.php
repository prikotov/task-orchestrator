<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Budget;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicBudgetCheckVo;

/**
 * Проверяет бюджет после раунда dynamic-цикла.
 */
interface CheckDynamicLoopBudgetServiceInterface
{
    /**
     * @param array<string, float> $roleCosts
     */
    public function checkAfterTurn(
        ?DynamicLoopBudgetVo $budget,
        float $totalCost,
        array $roleCosts,
        string $role,
        float $stepCost,
        bool $warning80Logged,
    ): ?DynamicBudgetCheckVo;
}
