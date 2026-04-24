<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Enum\OrchestrateExitCodeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;

/**
 * Маппит Domain-исключения и результаты оркестрации в типизированные exit codes.
 *
 * Presentation-слой (Command) зависит только от этого интерфейса (Application),
 * а не от Domain-исключений напрямую.
 */
interface ResolveExitCodeServiceInterface
{
    /**
     * Определяет exit code по типу исключения.
     */
    public function resolveFromThrowable(\Throwable $e): OrchestrateExitCodeEnum;

    /**
     * Определяет exit code по результату оркестрации.
     */
    public function resolveFromResult(OrchestrateChainResultDto $result, bool $isDynamic): OrchestrateExitCodeEnum;
}
