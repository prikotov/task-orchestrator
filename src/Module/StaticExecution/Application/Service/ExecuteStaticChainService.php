<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Application\Service;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\RunStaticChainService;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\ValueObject\StaticChainResultVo;

/**
 * Application-обёртка: делегирует static-chain выполнение в Domain-сервис.
 */
final readonly class ExecuteStaticChainService implements ExecuteStaticChainServiceInterface
{
    public function __construct(
        private RunStaticChainService $staticChainRunner,
    ) {
    }

    #[Override]
    public function execute(
        ChainDefinitionVo $chain,
        string $task,
        ?string $workingDir = null,
        int $timeout = 300,
        ?AuditLoggerInterface $auditLogger = null,
        bool $noContextFiles = false,
    ): StaticChainResultVo {
        return $this->staticChainRunner->execute(
            $chain,
            $task,
            $workingDir,
            $timeout,
            $auditLogger,
            $noContextFiles,
        );
    }
}
