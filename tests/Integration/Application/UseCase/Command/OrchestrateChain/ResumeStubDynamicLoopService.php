<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicLoopResultVo;

/**
 * Стаб RunDynamicLoopServiceInterface для resume-тестов.
 *
 * Расширяет базовый StubDynamicLoopService, добавляя захват параметров
 * startRound, initialDiscussionHistory, initialFacilitatorJournal и context
 * для последующих проверок в тестах.
 */
final class ResumeStubDynamicLoopService extends StubDynamicLoopService
{
    private ?int $capturedStartRound = null;

    private ?string $capturedHistory = null;

    private ?string $capturedJournal = null;

    private ?DynamicChainContextVo $capturedContext = null;

    #[Override]
    public function execute(
        DynamicChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?AuditLoggerInterface $auditLogger = null,
    ): DynamicLoopResultVo {
        $this->capturedContext = $context;
        $this->capturedStartRound = $startRound;
        $this->capturedHistory = $initialDiscussionHistory;
        $this->capturedJournal = $initialFacilitatorJournal;

        return parent::execute($chain, $context, $startRound, $initialDiscussionHistory, $initialFacilitatorJournal, $auditLogger);
    }

    public function getCapturedStartRound(): ?int
    {
        return $this->capturedStartRound;
    }

    public function getCapturedHistory(): ?string
    {
        return $this->capturedHistory;
    }

    public function getCapturedJournal(): ?string
    {
        return $this->capturedJournal;
    }

    public function getCapturedContext(): ?DynamicChainContextVo
    {
        return $this->capturedContext;
    }
}
