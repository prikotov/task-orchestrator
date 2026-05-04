<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use Override;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\TurnBreakVo;

/**
 * Координатор dynamic-цикла: итерации, условия выхода, оркестрация сервисов.
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
        private DynamicLoopSessionLoggerInterface $sessionLogger,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function execute(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?DynamicLoopAuditLoggerInterface $auditLogger = null,
    ): DynamicLoopResultVo {
        $execution = $this->initExecution(
            $startRound,
            $initialDiscussionHistory,
            $initialFacilitatorJournal,
        );
        $budget = $chain->getBudget();
        $startTime = microtime(true);

        $auditLogger?->logChainStart($chain->getName(), $context->topic);

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
                $chain->getName(),
                $startTime,
                $execution,
            ),
        );

        return $execution->toLoopResultVo();
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
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
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
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

    public static function calculateFinalizeReserve(int $maxTime): int
    {
        return max(
            self::FINALIZE_RESERVE_MIN_SECONDS,
            (int) round((float) $maxTime * self::FINALIZE_RESERVE_PERCENT),
        );
    }
}
