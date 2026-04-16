<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Budget;

use TasK\Orchestrator\Domain\ValueObject\BudgetVo;
use TasK\Orchestrator\Domain\ValueObject\DynamicBudgetCheckVo;

/**
 * Проверяет бюджет после раунда dynamic-цикла.
 *
 * Чистая функция: принимает primitives + BudgetVo, возвращает VO без побочных эффектов.
 */
interface CheckDynamicBudgetServiceInterface
{
    /**
     * @param array<string, float> $roleCosts текущие накопленные расходы по ролям
     */
    public function checkAfterTurn(
        ?BudgetVo $budget,
        float $totalCost,
        array $roleCosts,
        string $role,
        float $stepCost,
        bool $warning80Logged,
    ): ?DynamicBudgetCheckVo;
}
