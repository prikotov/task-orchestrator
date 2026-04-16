<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\ValueObject\FacilitatorTurnResultVo;

/**
 * Форматирование и ведение журнала dynamic-цикла.
 */
interface FormatDynamicJournalServiceInterface
{
    /**
     * Форматирует запись для facilitator turn.
     */
    public function formatFacilitatorEntry(int $step, int $round, FacilitatorTurnResultVo $fac): string;

    /**
     * Форматирует итоговую запись журнала.
     *
     * @param array{time: float, in: int, out: int, cost: float} $totals
     */
    public function formatFinalEntry(
        string $facilitatorJournal,
        array $totals,
        int $totalRounds,
        ?string $synthesis,
        bool $maxRoundsReached,
    ): string;

    /**
     * Форматирует запись facilitator journal для participant turn.
     */
    public function formatParticipantEntry(
        string $role,
        string $outputText,
        int $step,
        int $round,
    ): string;

    /**
     * Форматирует запись discussion history для participant turn.
     */
    public function formatDiscussionEntry(
        string $role,
        string $outputText,
    ): string;
}
