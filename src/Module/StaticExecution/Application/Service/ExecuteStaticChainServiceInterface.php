<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Application\Service;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\ValueObject\StaticChainResultVo;

/**
 * Исполнитель static-цепочки: линейное выполнение шагов.
 */
interface ExecuteStaticChainServiceInterface
{
    /**
     * Выполняет static-цепочку: линейное выполнение шагов с итерациями, budget, quality gates.
     */
    public function execute(
        ChainDefinitionVo $chain,
        string $task,
        ?string $workingDir = null,
        int $timeout = 300,
        ?AuditLoggerInterface $auditLogger = null,
        bool $noContextFiles = false,
    ): StaticChainResultVo;
}
