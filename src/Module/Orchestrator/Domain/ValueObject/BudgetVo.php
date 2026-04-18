<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

use InvalidArgumentException;

use function sprintf;

/**
 * Value Object бюджетных ограничений для цепочки AI-агентов.
 *
 * Immutable VO, задаёт лимиты стоимости:
 * - maxCostTotal — максимальная суммарная стоимость цепочки в USD (null = безлимит)
 * - maxCostPerStep — максимальная стоимость одного шага в USD (null = безлимит)
 * - perRoleBudgets — лимиты для конкретных ролей (ключ = имя роли, значение = BudgetVo)
 *
 * Бюджет проверяется в CheckDynamicBudgetService (Infrastructure):
 * - перед каждым шагом: isWithinTotalBudget(currentTotalCost) + isWithinRoleBudget(role, roleCost)
 * - после каждого шага: isWithinStepBudget(stepCost) + isWithinRoleBudget(role, roleCost)
 */
final readonly class BudgetVo
{
    /**
     * @param array<string, BudgetVo> $perRoleBudgets лимиты по ролям (ключ = имя роли)
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

    /**
     * Возвращает per-role бюджет для указанной роли или null.
     */
    public function getRoleBudget(string $role): ?BudgetVo
    {
        return $this->perRoleBudgets[$role] ?? null;
    }

    /**
     * @return array<string, BudgetVo>
     */
    public function getPerRoleBudgets(): array
    {
        return $this->perRoleBudgets;
    }

    /**
     * Есть ли хотя бы один per-role бюджет.
     */
    public function hasRoleBudgets(): bool
    {
        return $this->perRoleBudgets !== [];
    }

    /**
     * Является ли бюджет безлимитным (оба лимита null и нет per-role)?
     */
    public function isUnlimited(): bool
    {
        return $this->maxCostTotal === null && $this->maxCostPerStep === null && !$this->hasRoleBudgets();
    }

    /**
     * Находится ли суммарная стоимость в пределах total-бюджета?
     *
     * Если maxCostTotal не задан (null) — всегда true.
     */
    public function isWithinTotalBudget(float $spentTotal): bool
    {
        if ($this->maxCostTotal === null) {
            return true;
        }

        return $spentTotal <= $this->maxCostTotal;
    }

    /**
     * Находится ли стоимость шага в пределах step-бюджета?
     *
     * Если maxCostPerStep не задан (null) — всегда true.
     */
    public function isWithinStepBudget(float $stepCost): bool
    {
        if ($this->maxCostPerStep === null) {
            return true;
        }

        return $stepCost <= $this->maxCostPerStep;
    }

    /**
     * Находится ли накопленная стоимость роли в пределах role-бюджета?
     *
     * Если для роли нет per-role бюджета — всегда true.
     */
    public function isWithinRoleBudget(string $role, float $spentByRole): bool
    {
        $roleBudget = $this->perRoleBudgets[$role] ?? null;

        return $roleBudget === null || $roleBudget->isWithinTotalBudget($spentByRole);
    }

    /**
     * Находится ли стоимость шага роли в пределах role step-бюджета?
     *
     * Если для роли нет per-role бюджета или role maxCostPerStep не задан — всегда true.
     */
    public function isWithinRoleStepBudget(string $role, float $stepCost): bool
    {
        $roleBudget = $this->perRoleBudgets[$role] ?? null;

        return $roleBudget === null || $roleBudget->isWithinStepBudget($stepCost);
    }

    /**
     * Достигнут ли порог близости к total-лимиту (по умолчанию 80%).
     *
     * Возвращает true, если расходы >= threshold * maxCostTotal,
     * но ещё не превысили лимит. Если maxCostTotal не задан — false.
     */
    public function isNearTotalBudget(float $spent, float $threshold = 0.8): bool
    {
        if ($this->maxCostTotal === null) {
            return false;
        }

        return $spent >= ($this->maxCostTotal * $threshold) && $spent <= $this->maxCostTotal;
    }

    /**
     * Создаёт бюджет из массива конфигурации (YAML-параметры).
     *
     * @param array{
     *     max_cost_total?: float|int|null,
     *     max_cost_per_step?: float|int|null,
     *     per_role?: array<string, array{max_cost_total?: float|int|null, max_cost_per_step?: float|int|null}|mixed>
     * } $config
     */
    public static function fromArray(array $config): self
    {
        $maxCostTotal = $config['max_cost_total'] ?? null;
        $maxCostPerStep = $config['max_cost_per_step'] ?? null;

        $perRoleBudgets = [];
        foreach ($config['per_role'] ?? [] as $role => $roleConfig) {
            if (\is_array($roleConfig)) {
                $perRoleBudgets[$role] = self::fromArray($roleConfig);
            }
        }

        return new self(
            maxCostTotal: $maxCostTotal !== null ? (float) $maxCostTotal : null,
            maxCostPerStep: $maxCostPerStep !== null ? (float) $maxCostPerStep : null,
            perRoleBudgets: $perRoleBudgets,
        );
    }

    /**
     * Возвращает строковое представление для логирования.
     */
    public function toLogString(): string
    {
        $roleParts = '';
        foreach ($this->perRoleBudgets as $role => $budget) {
            $roleParts .= sprintf(', %s=(%s)', $role, $budget->toShortString());
        }

        return sprintf(
            'Budget(maxCostTotal=%s, maxCostPerStep=%s%s)',
            $this->maxCostTotal !== null ? sprintf('%.2f', $this->maxCostTotal) : 'unlimited',
            $this->maxCostPerStep !== null ? sprintf('%.2f', $this->maxCostPerStep) : 'unlimited',
            $roleParts,
        );
    }

    /**
     * Краткое строковое представление (без per-role) для вложенного использования.
     */
    public function toShortString(): string
    {
        return sprintf(
            'total=%s, step=%s',
            $this->maxCostTotal !== null ? sprintf('%.2f', $this->maxCostTotal) : '∞',
            $this->maxCostPerStep !== null ? sprintf('%.2f', $this->maxCostPerStep) : '∞',
        );
    }
}
