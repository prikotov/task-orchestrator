<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Dto\ChainResultAuditDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Dto\StepAuditStatusDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\FacilitatorResponseParserInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainTurnResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo;

/**
 * Финализация dynamic-цикла: запуск finalize turn'а, форматирование журнала, аудит.
 *
 * Отвечает за завершающие действия после основного цикла:
 * - запуск finalize step для получения synthesis
 * - форматирование финальной записи facilitator journal
 * - построение audit DTO
 */
final readonly class FinalizeDynamicLoopService implements FinalizeDynamicLoopServiceInterface
{
    public function __construct(
        private ExecuteDynamicTurnServiceInterface $turnExecutor,
        private FormatDynamicJournalServiceInterface $journal,
        private ChainSessionLoggerInterface $sessionLogger,
        private FacilitatorResponseParserInterface $facParser,
    ) {
    }

    /**
     * Запускает finalize turn: вызывает агента для получения synthesis.
     */
    #[Override]
    public function executeFinalizeTurn(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
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

    /**
     * Форматирует финальную запись facilitator journal и записывает в файл.
     */
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

    /**
     * Строит audit DTO для логирования результата цепочки.
     */
    #[Override]
    public function buildChainAuditDto(
        string $chainName,
        float $startTime,
        DynamicLoopExecution $execution,
    ): ChainResultAuditDto {
        return new ChainResultAuditDto(
            chainName: $chainName,
            totalDurationMs: (microtime(true) - $startTime) * 1000.0,
            totalInputTokens: $execution->getTotals()['in'],
            totalOutputTokens: $execution->getTotals()['out'],
            totalCost: $execution->getTotals()['cost'],
            budgetExceeded: $execution->getBudgetBreak()?->budgetExceeded ?? false,
            stepsCount: count($execution->getRoundResults()),
            stepStatuses: array_map(
                static fn(DynamicRoundResultVo $round): StepAuditStatusDto => new StepAuditStatusDto(
                    $round->isError,
                ),
                $execution->getRoundResults(),
            ),
        );
    }
}
