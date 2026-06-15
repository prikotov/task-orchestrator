<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;

/**
 * Owned mutable-компонент агрегата DynamicLoopExecution: накопление метрик и round results.
 *
 * @internal Owned by DynamicLoopExecution. Не самостоятельная сущность и не aggregate root.
 *
 * Инкапсулирует:
 * - накопление totals (time, inputTokens, outputTokens, cost)
 * - список round results
 * - стоимость по ролям (roleCosts)
 *
 * recordRound() атомарен: добавление результата и накопление totals выполняются вместе.
 */
final class DynamicLoopMetrics
{
    private float $totalTime = 0.0;
    private int $totalInputTokens = 0;
    private int $totalOutputTokens = 0;
    private float $totalCost = 0.0;

    /** @var array<string, float> role → суммарная стоимость */
    private array $roleCosts = [];

    /** @var list<DynamicRoundResultVo> */
    private array $roundResults = [];

    /**
     * Записывает результат выполненного раунда: добавляет в список и аккумулирует метрики.
     *
     * Атомарная операция — append и accumulate выполняются вместе.
     */
    public function recordRound(DynamicRoundResultVo $roundResult): void
    {
        $this->roundResults[] = $roundResult;
        $this->totalTime += $roundResult->duration;
        $this->totalInputTokens += $roundResult->inputTokens;
        $this->totalOutputTokens += $roundResult->outputTokens;
        $this->totalCost += $roundResult->cost;
    }

    public function addRoleCost(string $role, float $cost): void
    {
        $this->roleCosts[$role] = ($this->roleCosts[$role] ?? 0.0) + $cost;
    }

    /**
     * @return list<DynamicRoundResultVo>
     */
    public function getRoundResults(): array
    {
        return $this->roundResults;
    }

    /**
     * @return array{time: float, in: int, out: int, cost: float}
     */
    public function getTotals(): array
    {
        return [
            'time' => $this->totalTime,
            'in' => $this->totalInputTokens,
            'out' => $this->totalOutputTokens,
            'cost' => $this->totalCost,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function getRoleCosts(): array
    {
        return $this->roleCosts;
    }

    public function getTotalCost(): float
    {
        return $this->totalCost;
    }
}
