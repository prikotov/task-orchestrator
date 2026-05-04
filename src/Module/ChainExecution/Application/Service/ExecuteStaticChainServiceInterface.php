<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\Service;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\StaticAuditServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStaticChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticChainResultVo;

/**
 * Исполнитель static-цепочки: линейное выполнение шагов.
 */
interface ExecuteStaticChainServiceInterface
{
    /**
     * Выполняет static-цепочку: линейное выполнение шагов с итерациями, budget, quality gates.
     */
    public function execute(
        ExecutionStaticChainConfigVo $chain,
        string $task,
        ?string $workingDir = null,
        int $timeout = 300,
        ?StaticAuditServiceInterface $auditService = null,
        bool $noContextFiles = false,
    ): StaticChainResultVo;
}
