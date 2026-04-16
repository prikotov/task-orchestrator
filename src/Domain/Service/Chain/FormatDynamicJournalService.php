<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\ValueObject\FacilitatorTurnResultVo;
use Override;

use function date;
use function sprintf;

/**
 * Форматирование и ведение журнала dynamic-цикла.
 */
final readonly class FormatDynamicJournalService implements FormatDynamicJournalServiceInterface
{
    #[Override]
    public function formatFacilitatorEntry(int $step, int $round, FacilitatorTurnResultVo $fac): string
    {
        $now = date('H:i:s');
        $datePrefix = date('Y-m-d');
        $dur = round($fac->roundResult->duration, 1);
        $header = sprintf(
            "[%s %s] Step %d | Round %d | %s",
            $datePrefix,
            $now,
            $step,
            $round,
            $fac->roundResult->duration !== 0.0 ? "{$dur}s" : '',
        );

        if ($fac->done) {
            return sprintf("%s → synthesis (завершил обсуждение)\n", $header);
        }

        if ($fac->nextRole !== null) {
            return sprintf("%s → дал слово: %s\n", $header, $fac->nextRole);
        }

        return '';
    }

    #[Override]
    public function formatFinalEntry(
        string $facilitatorJournal,
        array $totals,
        int $totalRounds,
        ?string $synthesis,
        bool $maxRoundsReached,
    ): string {
        $status = $synthesis !== null
            ? ($maxRoundsReached ? 'max_rounds_reached' : 'facilitator_done')
            : 'interrupted';

        return $facilitatorJournal . sprintf(
            "\n[%s %s] ═══ ИТОГ ═══\n"
            . "Статус: %s\n"
            . "Раундов: %d | Время: %.1fs | Токены: %d / %d | Стоимость: $%.4f\n"
            . "Synthesis: %s\n",
            date('Y-m-d'),
            date('H:i:s'),
            $status,
            $totalRounds,
            $totals['time'],
            $totals['in'],
            $totals['out'],
            $totals['cost'],
            $synthesis !== null ? 'да' : 'нет',
        );
    }

    #[Override]
    public function formatParticipantEntry(
        string $role,
        string $outputText,
        int $step,
        int $round,
    ): string {
        return sprintf(
            "\n[%s %s] Step %d | Round %d | %s выступил\n",
            date('Y-m-d'),
            date('H:i:s'),
            $step,
            $round,
            $role,
        );
    }

    #[Override]
    public function formatDiscussionEntry(
        string $role,
        string $outputText,
    ): string {
        return sprintf("\n\n# 👤 %s\n\n%s", $role, $outputText);
    }
}
