<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicBudgetCheckVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;

/**
 * In-memory сущность состояния dynamic-цикла.
 *
 * Инкапсулирует мутабельное состояние выполнения dynamic-цепочки:
 * - накопление метрик (time, tokens, cost) и round results — через DynamicLoopMetrics
 * - journal entries (discussion history, facilitator journal) — через DynamicLoopJournal
 * - трекинг раундов и шагов
 * - бюджетный state (80% warning, exceeded)
 *
 * Не персистентная — живёт только в рамках одного вызова runDynamicLoop().
 */
final class DynamicLoopExecution
{
    // ─── Owned components ──────────────────────────────────────────────

    private DynamicLoopMetrics $metrics;
    private DynamicLoopJournal $journal;

    // ─── Counters ──────────────────────────────────────────────────────

    private int $step;
    private int $round;
    private int $participantRounds;

    // ─── Result ────────────────────────────────────────────────────────

    private ?string $synthesis = null;
    private bool $maxRoundsReached = false;
    private ?string $interruptionReason = null;
    private ?DynamicBudgetCheckVo $budgetBreak = null;
    private bool $budgetWarning80Logged = false;
    private bool $maxTimeExceeded = false;

    public function __construct(
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
    ) {
        $this->step = $startRound;
        $this->round = 0;
        $this->participantRounds = 0;
        $this->metrics = new DynamicLoopMetrics();
        $this->journal = new DynamicLoopJournal(
            $initialDiscussionHistory,
            $initialFacilitatorJournal,
        );
    }

    // ─── Owned component accessors ─────────────────────────────────────

    public function getMetrics(): DynamicLoopMetrics
    {
        return $this->metrics;
    }

    public function getJournal(): DynamicLoopJournal
    {
        return $this->journal;
    }

    // ─── Getters ───────────────────────────────────────────────────────

    public function getStep(): int
    {
        return $this->step;
    }

    public function getRound(): int
    {
        return $this->round;
    }

    public function getParticipantRounds(): int
    {
        return $this->participantRounds;
    }

    public function getDiscussionHistory(): string
    {
        return $this->journal->getDiscussionHistory();
    }

    public function getFacilitatorJournal(): string
    {
        return $this->journal->getFacilitatorJournal();
    }

    public function getFacilitatorSummary(): string
    {
        return $this->journal->getFacilitatorSummary();
    }

    public function getSynthesis(): ?string
    {
        return $this->synthesis;
    }

    public function isMaxRoundsReached(): bool
    {
        return $this->maxRoundsReached;
    }

    public function getInterruptionReason(): ?string
    {
        return $this->interruptionReason;
    }

    public function getBudgetBreak(): ?DynamicBudgetCheckVo
    {
        return $this->budgetBreak;
    }

    public function isBudgetWarning80Logged(): bool
    {
        return $this->budgetWarning80Logged;
    }

    public function isMaxTimeExceeded(): bool
    {
        return $this->maxTimeExceeded;
    }

    /**
     * @return list<DynamicRoundResultVo>
     */
    public function getRoundResults(): array
    {
        return $this->metrics->getRoundResults();
    }

    /**
     * @return array{time: float, in: int, out: int, cost: float}
     */
    public function getTotals(): array
    {
        return $this->metrics->getTotals();
    }

    /**
     * @return array<string, float>
     */
    public function getRoleCosts(): array
    {
        return $this->metrics->getRoleCosts();
    }

    public function getTotalCost(): float
    {
        return $this->metrics->getTotalCost();
    }

    // ─── Counter mutations ─────────────────────────────────────────────

    public function advanceStep(): void
    {
        $this->step++;
    }

    public function advanceRound(): void
    {
        $this->round++;
    }

    public function advanceParticipantRounds(): void
    {
        $this->participantRounds++;
    }

    // ─── Journal setters (compatibility delegates) ─────────────────────

    public function setFacilitatorJournal(string $journal): void
    {
        $this->journal->setFacilitatorJournal($journal);
    }

    public function setDiscussionHistory(string $history): void
    {
        $this->journal->setDiscussionHistory($history);
    }

    // ─── Result mutations ──────────────────────────────────────────────

    public function setSynthesis(?string $synthesis): void
    {
        $this->synthesis = $synthesis;
    }

    public function markMaxRoundsReached(bool $reached): void
    {
        $this->maxRoundsReached = $reached;
    }

    public function setInterruptionReason(?string $reason): void
    {
        $this->interruptionReason = $reason;
    }

    public function setBudgetBreak(?DynamicBudgetCheckVo $break): void
    {
        $this->budgetBreak = $break;
    }

    public function markBudgetWarning80Logged(): void
    {
        $this->budgetWarning80Logged = true;
    }

    public function markMaxTimeExceeded(): void
    {
        $this->maxTimeExceeded = true;
    }

    // ─── Init from resume ─────────────────────────────────────────────

    /**
     * Восстанавливает round/participantRounds из файлов сессии.
     *
     * @param list<array{round?: int, is_facilitator: bool}> $roundFiles
     */
    public function restoreFromRoundFiles(array $roundFiles): void
    {
        foreach ($roundFiles as $data) {
            $round = $data['round'] ?? 0;
            if ($round > $this->round) {
                $this->round = $round;
            }
            if (!$data['is_facilitator']) {
                $this->participantRounds++;
            }
        }
    }

    /**
     * Формирует DTO финального результата dynamic-цикла.
     */
    public function toLoopResultVo(): DynamicLoopResultVo
    {
        $totals = $this->metrics->getTotals();

        return new DynamicLoopResultVo(
            roundResults: $this->metrics->getRoundResults(),
            totalTime: $totals['time'],
            totalInputTokens: $totals['in'],
            totalOutputTokens: $totals['out'],
            totalCost: $totals['cost'],
            synthesis: $this->synthesis,
            maxRoundsReached: $this->maxRoundsReached,
            interruptionReason: $this->interruptionReason,
            budgetExceeded: $this->budgetBreak?->budgetExceeded ?? false, // @phpstan-ignore nullsafe.neverNull
            budgetLimit: $this->budgetBreak?->budgetLimit ?? 0.0, // @phpstan-ignore nullsafe.neverNull
            budgetExceededRole: $this->budgetBreak?->budgetExceededRole,
            maxTimeExceeded: $this->maxTimeExceeded,
        );
    }
}
