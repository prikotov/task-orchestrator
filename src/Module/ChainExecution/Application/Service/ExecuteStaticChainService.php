<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\Service;

use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\RunStaticChainService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\StaticAuditServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStaticChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticChainResultVo;

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
        ExecutionStaticChainConfigVo $chain,
        string $task,
        ?string $workingDir = null,
        int $timeout = 300,
        ?StaticAuditServiceInterface $auditService = null,
        bool $noContextFiles = false,
    ): StaticChainResultVo {
        return $this->staticChainRunner->execute(
            $chain,
            $task,
            $workingDir,
            $timeout,
            $auditService,
            $noContextFiles,
        );
    }
}
