<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FixIterationGroupVo;

/**
 * In-memory сущность выполнения static-цепочки.
 *
 * Инкапсулирует мутабельное состояние и бизнес-правила навигации:
 * - бюджетные проверки (перед/после шага)
 * - итерационные циклы (fix iteration groups)
 * - навигация по шагам (advance, jump-back)
 *
 * Не персистентная — живёт только в рамках одного вызова runStaticChain().
 * Логирование не выполняет — это ответственность Application-слоя.
 */
final class StaticChainExecution
{
    private ?string $previousContext = null;
    private float $totalTime = 0.0;
    private int $totalInputTokens = 0;
    private int $totalOutputTokens = 0;
    private float $totalCost = 0.0;
    private int $stepIndex = 0;
    private int $totalIterations = 0;

    /** @var array<string, int> groupName → номер итерации */
    private array $groupIterations = [];

    /** @var array<string, float> role → суммарная стоимость */
    private array $roleCosts = [];

    private bool $budgetWarning80Logged = false;

    /** @var array{exceeded: bool, limit: float, role: string|null} */
    private array $budgetState = ['exceeded' => false, 'limit' => 0.0, 'role' => null];

    public function isComplete(int $stepCount): bool
    {
        return $this->stepIndex >= $stepCount;
    }

    public function getStepIndex(): int
    {
        return $this->stepIndex;
    }

    public function getPreviousContext(): ?string
    {
        return $this->previousContext;
    }

    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    public function getTotalTime(): float
    {
        return $this->totalTime;
    }

    public function getTotalInputTokens(): int
    {
        return $this->totalInputTokens;
    }

    public function getTotalOutputTokens(): int
    {
        return $this->totalOutputTokens;
    }

    public function getTotalIterations(): int
    {
        return $this->totalIterations;
    }

    public function isBudgetExceeded(): bool
    {
        return $this->budgetState['exceeded'];
    }

    public function getBudgetLimit(): float
    {
        return $this->budgetState['limit'];
    }

    public function getBudgetExceededRole(): ?string
    {
        return $this->budgetState['role'];
    }

    /**
     * Возвращает накопленную стоимость по роли.
     */
    public function getRoleCost(string $role): float
    {
        return $this->roleCosts[$role] ?? 0.0;
    }

    /**
     * Записывает результат выполненного шага.
     */
    public function recordStep(
        string $outputText,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        float $duration,
        string $role,
    ): void {
        $this->previousContext = $outputText;
        $this->totalInputTokens += $inputTokens;
        $this->totalOutputTokens += $outputTokens;
        $this->totalCost += $cost;
        $this->totalTime += $duration;
        $this->roleCosts[$role] = ($this->roleCosts[$role] ?? 0.0) + $cost;
    }

    /**
     * Возвращает номер текущей итерации для группы (начиная с 1).
     *
     * Если шаг не в группе — возвращает null.
     */
    public function getIterationNumber(string $groupName): ?int
    {
        if (!isset($this->groupIterations[$groupName])) {
            $this->groupIterations[$groupName] = 1;
        }

        return $this->groupIterations[$groupName];
    }

    /**
     * Проверяет бюджет перед выполнением шага.
     *
     * @return array{limit: float, role: string|null}|null null если бюджет в норме
     */
    public function checkBudgetBeforeStep(?BudgetVo $budget, string $role): ?array
    {
        if ($budget === null) {
            return null;
        }

        if (!$budget->isWithinTotalBudget($this->totalCost)) {
            return ['limit' => $budget->getMaxCostTotal() ?? 0.0, 'role' => null];
        }

        $roleSpent = $this->roleCosts[$role] ?? 0.0;
        if (!$budget->isWithinRoleBudget($role, $roleSpent)) {
            return ['limit' => $budget->getRoleBudget($role)?->getMaxCostTotal() ?? 0.0, 'role' => $role];
        }

        return null;
    }

    /**
     * Проверяет бюджет после выполнения шага.
     *
     * @return array{limit: float, role: string|null}|null null если бюджет в норме
     */
    public function checkBudgetAfterStep(?BudgetVo $budget, string $role, float $stepCost): ?array
    {
        if ($budget === null) {
            return null;
        }

        if (!$budget->isWithinStepBudget($stepCost)) {
            return ['limit' => $budget->getMaxCostPerStep() ?? 0.0, 'role' => $role];
        }

        if (!$budget->isWithinRoleStepBudget($role, $stepCost)) {
            return ['limit' => $budget->getRoleBudget($role)?->getMaxCostPerStep() ?? 0.0, 'role' => $role];
        }

        if (!$budget->isWithinTotalBudget($this->totalCost)) {
            return ['limit' => $budget->getMaxCostTotal() ?? 0.0, 'role' => null];
        }

        $roleTotal = $this->roleCosts[$role] ?? 0.0;
        if (!$budget->isWithinRoleBudget($role, $roleTotal)) {
            return ['limit' => $budget->getRoleBudget($role)?->getMaxCostTotal() ?? 0.0, 'role' => $role];
        }

        return null;
    }

    /**
     * Проверяет, достигнут ли порог 80% бюджета (без повторного срабатывания).
     */
    public function isNearTotalBudget(?BudgetVo $budget): bool
    {
        if ($budget === null || $this->budgetWarning80Logged) {
            return false;
        }

        return $budget->isNearTotalBudget($this->totalCost);
    }

    /**
     * Отмечает, что предупреждение 80% бюджета залогировано.
     */
    public function markBudgetWarning80Logged(): void
    {
        $this->budgetWarning80Logged = true;
    }

    /**
     * Фиксирует превышение бюджета.
     */
    public function markBudgetExceeded(float $limit, ?string $role): void
    {
        $this->budgetState = ['exceeded' => true, 'limit' => $limit, 'role' => $role];
    }

    /**
     * Определяет группу итераций для текущего шага (по имени шага).
     *
     * Возвращает FixIterationGroupVo, если шаг является последним в группе.
     * Иначе — null (шаг не триггерит retry).
     *
     * @param list<FixIterationGroupVo> $fixIterations
     */
    public function findRetryGroup(ChainStepVo $step, array $fixIterations): ?FixIterationGroupVo
    {
        $stepName = $step->getName();
        if ($stepName === null) {
            return null;
        }

        foreach ($fixIterations as $group) {
            if ($group->isLastStep($stepName)) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Должен ли шаг триггерить retry группы?
     *
     * @techdebt 2026-04-09: retry всегда, пока iteration < max.
     * Реализовать анализ вывода reviewer (regex/classifier) для условного retry.
     */
    public function shouldRetryGroup(?FixIterationGroupVo $group): bool
    {
        if ($group === null) {
            return false;
        }

        $currentIteration = $this->groupIterations[$group->getGroup()] ?? 1;

        return $currentIteration < $group->getMaxIterations();
    }

    /**
     * Выполняет retry: увеличивает счётчик итераций и прыгает к началу группы.
     *
     * @param FixIterationGroupVo $group
     * @param array<string, int> $nameToIndexMap stepName → index в массиве steps
     */
    public function executeGroupRetry(
        FixIterationGroupVo $group,
        array $nameToIndexMap,
    ): void {
        $groupName = $group->getGroup();
        $firstStepName = $group->getStepNames()[0];

        $this->groupIterations[$groupName] = ($this->groupIterations[$groupName] ?? 1) + 1;
        $this->totalIterations++;
        $this->stepIndex = $nameToIndexMap[$firstStepName] ?? $this->stepIndex;
    }

    /**
     * Продвигает stepIndex к следующему шагу.
     */
    public function advance(): void
    {
        $this->stepIndex++;
    }
}
