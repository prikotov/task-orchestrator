<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;

/**
 * Форматирование бюджетных данных для записи в session.json и result.md.
 */
final class ChainSessionBudgetFormatter
{
    /**
     * Строит массив бюджетных данных для session.json.
     *
     * @return array{max_cost_total: float|null, max_cost_per_step: float|null, per_role?: array<string, array{max_cost_total: float|null, max_cost_per_step: float|null}>}
     */
    public function buildBudgetData(BudgetVo $budget): array
    {
        $data = [
            'max_cost_total' => $budget->getMaxCostTotal(),
            'max_cost_per_step' => $budget->getMaxCostPerStep(),
        ];

        foreach ($budget->getPerRoleBudgets() as $role => $roleBudget) {
            $data['per_role'][$role] = [
                'max_cost_total' => $roleBudget->getMaxCostTotal(),
                'max_cost_per_step' => $roleBudget->getMaxCostPerStep(),
            ];
        }

        return $data;
    }

    /**
     * Форматирует per-role бюджетную информацию для result.md.
     */
    public function formatPerRoleBudgetInfo(BudgetVo $budget): string
    {
        if (!$budget->hasRoleBudgets()) {
            return '';
        }

        $lines = "\n- Per-role budgets:";
        foreach ($budget->getPerRoleBudgets() as $role => $rb) {
            $lines .= sprintf(
                "\n  - %s: total=%s, step=%s",
                $role,
                $rb->getMaxCostTotal() !== null ? sprintf('$%.2f', $rb->getMaxCostTotal()) : '∞',
                $rb->getMaxCostPerStep() !== null ? sprintf('$%.2f', $rb->getMaxCostPerStep()) : '∞',
            );
        }

        return $lines;
    }

    /**
     * Форматирует значение бюджета для человекочитаемого вывода.
     */
    public function formatBudgetValue(?float $value): string
    {
        return $value !== null ? sprintf('$%.2f', $value) : 'unlimited';
    }
}
