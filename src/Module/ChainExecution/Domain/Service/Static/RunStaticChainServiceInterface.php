<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStaticChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticChainResultVo;

/**
 * Интерфейс доменного сервиса выполнения static-цепочки.
 */
interface RunStaticChainServiceInterface
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
