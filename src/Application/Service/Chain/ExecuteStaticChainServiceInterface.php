<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Application\Service\Chain;

use TasK\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TasK\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TasK\Orchestrator\Domain\Service\Chain\AuditLoggerInterface;
use TasK\Orchestrator\Domain\ValueObject\ChainDefinitionVo;

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
        AgentRunnerInterface $runner,
        string $runnerName,
        string $task,
        ?string $model = null,
        ?string $workingDir = null,
        int $timeout = 300,
        ?AuditLoggerInterface $auditLogger = null,
    ): OrchestrateChainResultDto;
}
