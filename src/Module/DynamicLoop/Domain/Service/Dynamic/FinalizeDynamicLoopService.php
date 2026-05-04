<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Dto\DynamicLoopAuditDto;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Dto\DynLoopStepAuditDto;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;

/**
 * Финализация dynamic-цикла: запуск finalize turn'а, форматирование журнала, аудит.
 */
final readonly class FinalizeDynamicLoopService implements FinalizeDynamicLoopServiceInterface
{
    public function __construct(
        private ExecuteDynamicTurnServiceInterface $turnExecutor,
        private FormatDynamicJournalServiceInterface $journal,
        private DynamicLoopSessionLoggerInterface $sessionLogger,
        private FacilitatorResponseParserInterface $facParser,
    ) {
    }

    #[Override]
    public function executeFinalizeTurn(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
    ): void {
        $execution->advanceStep();
        $execution->advanceRound();

        $turnResult = $this->turnExecutor->runFinalizeStep(
            $chain,
            $context,
            $execution,
            $auditLogger,
        );

        $dur = round($turnResult->duration, 1);
        $execution->appendFacilitatorJournal(sprintf(
            "[%s %s] Step %d | Round %d | %s → synthesis (финализация)\n",
            date('Y-m-d'),
            date('H:i'),
            $execution->getStep(),
            $execution->getRound(),
            $turnResult->duration !== 0.0 ? "{$dur}s" : '',
        ));
        $this->sessionLogger->writeContextFile(
            'facilitator_journal.md',
            $execution->getFacilitatorJournal(),
        );

        $raw = $turnResult->agentResult->getOutputText();
        $parsed = $this->facParser->parse($raw);
        $execution->setSynthesis($parsed->getSynthesis() ?? $raw);
    }

    #[Override]
    public function formatFinalJournal(DynamicLoopExecution $execution): void
    {
        $finalJournal = $this->journal->formatFinalEntry(
            $execution->getFacilitatorJournal(),
            $execution->getTotals(),
            count($execution->getRoundResults()),
            $execution->getSynthesis(),
            $execution->isMaxRoundsReached(),
        );
        $execution->setFacilitatorJournal($finalJournal);
        $this->sessionLogger->writeContextFile(
            'facilitator_journal.md',
            $execution->getFacilitatorJournal(),
        );
    }

    #[Override]
    public function buildChainAuditDto(
        string $chainName,
        float $startTime,
        DynamicLoopExecution $execution,
    ): DynamicLoopAuditDto {
        return new DynamicLoopAuditDto(
            chainName: $chainName,
            totalDurationMs: (microtime(true) - $startTime) * 1000.0,
            totalInputTokens: $execution->getTotals()['in'],
            totalOutputTokens: $execution->getTotals()['out'],
            totalCost: $execution->getTotals()['cost'],
            budgetExceeded: $execution->getBudgetBreak()?->budgetExceeded ?? false,
            stepsCount: count($execution->getRoundResults()),
            stepStatuses: array_map(
                static fn(DynamicRoundResultVo $round): DynLoopStepAuditDto => new DynLoopStepAuditDto(
                    $round->isError,
                ),
                $execution->getRoundResults(),
            ),
        );
    }
}
