<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper;

use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;

/**
 * Контракт маппера формата отчёта.
 */
interface ReportFormatMapperInterface
{
    public function map(OrchestrateChainResultDto $result, string $chainName, string $task): string;
}
