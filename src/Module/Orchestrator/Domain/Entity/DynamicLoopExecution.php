<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicBudgetCheckVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo;

/**
 * In-memory сущность состояния dynamic-цикла.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @todo Рассмотреть разделение на DynamicMetrics + DynamicJournal entity для снижения числа полей.
 *
 * Инкапсулирует мутабельное состояние выполнения dynamic-цепочки:
 * - накопление метрик (time, tokens, cost)
 * - трекинг раундов и шагов
 * - бюджетный state (80% warning, exceeded)
 * - journal entries (discussion history, facilitator journal)
 *
 * Не персистентная — живёт только в рамках одного вызова runDynamicLoop().
 */
final class DynamicLoopExecution
{
    // ─── Accumulators ──────────────────────────────────────────────────

    private float $totalTime = 0.0;
    private int $totalInputTokens = 0;
    private int $totalOutputTokens = 0;
    private float $totalCost = 0.0;

    /** @var array<string, float> role → суммарная стоимость */
    private array $roleCosts = [];

    // ─── Round results ─────────────────────────────────────────────────

    /** @var list<DynamicRoundResultVo> */
    private array $roundResults = [];

    // ─── Journal ───────────────────────────────────────────────────────

    private string $discussionHistory;
    private string $facilitatorJournal;
    private string $facilitatorSummary = '';

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
        $this->discussionHistory = $initialDiscussionHistory;
        $this->facilitatorJournal = $initialFacilitatorJournal;
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
        return $this->discussionHistory;
    }

    public function getFacilitatorJournal(): string
    {
        return $this->facilitatorJournal;
    }

    public function getFacilitatorSummary(): string
    {
        return $this->facilitatorSummary;
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

    // ─── Accumulators ──────────────────────────────────────────────────

    /**
     * Записывает результат выполненного раунда: добавляет в список и аккумулирует метрики.
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

    // ─── Journal mutations ─────────────────────────────────────────────

    public function appendFacilitatorJournal(string $entry): void
    {
        $this->facilitatorJournal .= $entry;
    }

    public function setFacilitatorJournal(string $journal): void
    {
        $this->facilitatorJournal = $journal;
    }

    public function appendDiscussionHistory(string $entry): void
    {
        $this->discussionHistory .= $entry;
    }

    public function setDiscussionHistory(string $history): void
    {
        $this->discussionHistory = $history;
    }

    public function appendFacilitatorSummary(string $entry): void
    {
        $this->facilitatorSummary .= $entry;
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
        return new DynamicLoopResultVo(
            roundResults: $this->roundResults,
            totalTime: $this->totalTime,
            totalInputTokens: $this->totalInputTokens,
            totalOutputTokens: $this->totalOutputTokens,
            totalCost: $this->totalCost,
            synthesis: $this->synthesis,
            maxRoundsReached: $this->maxRoundsReached,
            interruptionReason: $this->interruptionReason,
            budgetExceeded: $this->budgetBreak?->budgetExceeded ?? false,
            budgetLimit: $this->budgetBreak?->budgetLimit ?? 0.0,
            budgetExceededRole: $this->budgetBreak?->budgetExceededRole,
            maxTimeExceeded: $this->maxTimeExceeded,
        );
    }
}
