<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorTurnResultVo;

/**
 * Форматирование и ведение журнала dynamic-цикла.
 */
interface FormatDynamicJournalServiceInterface
{
    public function formatFacilitatorEntry(int $step, int $round, FacilitatorTurnResultVo $fac): string;

    /**
     * @param array{time: float, in: int, out: int, cost: float} $totals
     */
    public function formatFinalEntry(
        string $facilitatorJournal,
        array $totals,
        int $totalRounds,
        ?string $synthesis,
        bool $maxRoundsReached,
    ): string;

    public function formatParticipantEntry(
        string $role,
        string $outputText,
        int $step,
        int $round,
    ): string;

    public function formatDiscussionEntry(
        string $role,
        string $outputText,
    ): string;

    public function formatFacilitatorDiscussionEntry(
        string $facilitatorRole,
        bool $done,
        ?string $nextRole,
        ?string $challenge,
        ?string $synthesis,
    ): string;
}
