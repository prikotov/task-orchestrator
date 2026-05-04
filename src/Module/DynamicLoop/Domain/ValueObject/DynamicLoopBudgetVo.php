<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object бюджетных ограничений для dynamic-цикла.
 *
 * Копия ChainDefinition\BudgetVo, без зависимости от ChainDefinition.Domain.
 * Immutable VO, задаёт лимиты стоимости:
 * - maxCostTotal — максимальная суммарная стоимость в USD (null = безлимит)
 * - maxCostPerStep — максимальная стоимость одного шага в USD (null = безлимит)
 * - perRoleBudgets — лимиты для конкретных ролей
 */
final readonly class DynamicLoopBudgetVo
{
    /**
     * @param array<string, DynamicLoopBudgetVo> $perRoleBudgets лимиты по ролям (ключ = имя роли)
     */
    public function __construct(
        private ?float $maxCostTotal = null,
        private ?float $maxCostPerStep = null,
        private array $perRoleBudgets = [],
    ) {
        if ($maxCostTotal !== null && $maxCostTotal < 0.0) {
            throw new InvalidArgumentException('maxCostTotal must be >= 0 or null.');
        }

        if ($maxCostPerStep !== null && $maxCostPerStep < 0.0) {
            throw new InvalidArgumentException('maxCostPerStep must be >= 0 or null.');
        }
    }

    public function getMaxCostTotal(): ?float
    {
        return $this->maxCostTotal;
    }

    public function getMaxCostPerStep(): ?float
    {
        return $this->maxCostPerStep;
    }

    public function getRoleBudget(string $role): ?self
    {
        return $this->perRoleBudgets[$role] ?? null;
    }

    /**
     * @return array<string, DynamicLoopBudgetVo>
     */
    public function getPerRoleBudgets(): array
    {
        return $this->perRoleBudgets;
    }

    public function hasRoleBudgets(): bool
    {
        return $this->perRoleBudgets !== [];
    }

    public function isUnlimited(): bool
    {
        return $this->maxCostTotal === null && $this->maxCostPerStep === null && !$this->hasRoleBudgets();
    }

    public function isWithinTotalBudget(float $spentTotal): bool
    {
        if ($this->maxCostTotal === null) {
            return true;
        }

        return $spentTotal <= $this->maxCostTotal;
    }

    public function isWithinStepBudget(float $stepCost): bool
    {
        if ($this->maxCostPerStep === null) {
            return true;
        }

        return $stepCost <= $this->maxCostPerStep;
    }

    public function isWithinRoleBudget(string $role, float $spentByRole): bool
    {
        $roleBudget = $this->perRoleBudgets[$role] ?? null;

        return $roleBudget === null || $roleBudget->isWithinTotalBudget($spentByRole);
    }

    public function isWithinRoleStepBudget(string $role, float $stepCost): bool
    {
        $roleBudget = $this->perRoleBudgets[$role] ?? null;

        return $roleBudget === null || $roleBudget->isWithinStepBudget($stepCost);
    }

    public function isNearTotalBudget(float $spent, float $threshold = 0.8): bool
    {
        if ($this->maxCostTotal === null) {
            return false;
        }

        return $spent >= ($this->maxCostTotal * $threshold) && $spent <= $this->maxCostTotal;
    }
}
