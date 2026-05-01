<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use Override;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\TurnBreakVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\TurnContinueVo;

/**
 * Координатор dynamic-цикла: итерации, условия выхода, оркестрация сервисов.
 *
 * Делегирует выполнение шагов ExecuteDynamicTurnService,
 * бюджет — CheckDynamicLoopBudgetService,
 * финализацию — FinalizeDynamicLoopService.
 *
 * Сам отвечает только за:
 * - инициализацию execution entity
 * - проверку времени (shouldReserveForFinalize)
 * - основную петлю цикла (while loop)
 * - маршрутизацию TurnContinueVo|TurnBreakVo
 * - вызов финализатора
 */
final readonly class RunDynamicLoopService implements RunDynamicLoopServiceInterface
{
    /** @var float Доля maxTime, резервируемая на finalize (10%) */
    private const float FINALIZE_RESERVE_PERCENT = 0.1;

    /** @var int Минимальный резерв времени на finalize в секундах */
    private const int FINALIZE_RESERVE_MIN_SECONDS = 60;

    public function __construct(
        private ExecuteDynamicTurnServiceInterface $turnExecutor,
        private FinalizeDynamicLoopServiceInterface $finalizer,
        private ChainSessionLoggerInterface $sessionLogger,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function execute(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?AuditLoggerInterface $auditLogger = null,
    ): DynamicLoopResultVo {
        $execution = $this->initExecution(
            $startRound,
            $initialDiscussionHistory,
            $initialFacilitatorJournal,
        );
        $budget = $chain->getSharedDefinition()->getBudget();
        $startTime = microtime(true);

        $auditLogger?->logChainStart($chain->getSharedDefinition()->getName(), $context->topic);

        while ($execution->getParticipantRounds() < $context->maxRounds) {
            if ($this->shouldReserveForFinalize($context->maxTime, $startTime)) {
                $this->applyTimeReserve($execution, $context->maxTime, $startTime);
                break;
            }

            $execution->advanceStep();
            $execution->advanceRound();

            $facResult = $this->turnExecutor->runFacilitatorTurn(
                $chain, $context, $execution, $budget, $auditLogger,
            );
            if ($facResult instanceof TurnBreakVo) {
                $this->applyBreakResult($execution, $facResult);
                break;
            }

            $partResult = $this->turnExecutor->runParticipantTurn(
                $chain, $context, $execution, $budget, $auditLogger,
                $facResult->nextRole, $facResult->challenge,
            );
            if ($partResult instanceof TurnBreakVo) {
                $this->applyBreakResult($execution, $partResult);
                break;
            }
        }

        $this->finalizeIfNeeded($chain, $context, $execution, $auditLogger);

        $this->finalizer->formatFinalJournal($execution);

        $auditLogger?->logChainResult(
            $this->finalizer->buildChainAuditDto(
                $chain->getSharedDefinition()->getName(),
                $startTime,
                $execution,
            ),
        );

        return $execution->toLoopResultVo();
    }

    // ─── State management ──────────────────────────────────────────────

    /**
     * @psalm-suppress ArgumentTypeCoercion getRoundFiles() returns array<int, array{...}> not list<array{...}>
     */
    private function initExecution(
        int $startRound,
        string $initialDiscussionHistory,
        string $initialFacilitatorJournal,
    ): DynamicLoopExecution {
        $execution = new DynamicLoopExecution(
            startRound: $startRound,
            initialDiscussionHistory: $initialDiscussionHistory,
            initialFacilitatorJournal: $initialFacilitatorJournal,
        );

        if ($startRound > 0) {
            $roundFiles = $this->sessionLogger->getRoundFiles();
            $execution->restoreFromRoundFiles($roundFiles);
        }

        return $execution;
    }

    private function applyBreakResult(
        DynamicLoopExecution $execution,
        TurnBreakVo $result,
    ): void {
        $execution->setBudgetBreak($result->budgetResult);
        $execution->setInterruptionReason($result->interruptionReason);
        $execution->setSynthesis($result->synthesis);
    }

    private function finalizeIfNeeded(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
    ): void {
        $execution->markMaxRoundsReached(
            $execution->getSynthesis() === null
            && $execution->getParticipantRounds() >= $context->maxRounds
            && !$execution->isMaxTimeExceeded(),
        );

        if (($execution->isMaxRoundsReached() || $execution->isMaxTimeExceeded()) && $execution->getSynthesis() === null) {
            $this->finalizer->executeFinalizeTurn(
                $chain,
                $context,
                $execution,
                $auditLogger,
            );
        }
    }

    // ─── Time checks ────────────────────────────────────────────────────

    private function shouldReserveForFinalize(?int $maxTime, float $startTime): bool
    {
        if ($maxTime === null) {
            return false;
        }

        $elapsed = microtime(true) - $startTime;
        $remaining = (float) $maxTime - $elapsed;

        return $remaining <= self::calculateFinalizeReserve($maxTime);
    }

    private function applyTimeReserve(
        DynamicLoopExecution $execution,
        ?int $maxTime,
        float $startTime,
    ): void {
        $execution->markMaxTimeExceeded();
        assert($maxTime !== null);
        $reserve = self::calculateFinalizeReserve($maxTime);
        $this->logger?->info('Discussion stopped: reserving time for synthesis.', [
            'maxTime' => $maxTime,
            'elapsed' => round(microtime(true) - $startTime, 1),
            'finalizeReserve' => $reserve,
        ]);
        $execution->appendFacilitatorJournal(sprintf(
            "[%s %s] Дискуссия остановлена: резервирование времени на синтез (reserve=%ds)\n",
            date('Y-m-d'),
            date('H:i'),
            $reserve,
        ));
    }

    /**
     * Вычисляет резерв времени на finalize в секундах.
     *
     * Формула: max(60, maxTime * 10%). Гарантирует минимум 60 секунд на синтез.
     */
    public static function calculateFinalizeReserve(int $maxTime): int
    {
        return max(
            self::FINALIZE_RESERVE_MIN_SECONDS,
            (int) round((float) $maxTime * self::FINALIZE_RESERVE_PERCENT),
        );
    }
}
