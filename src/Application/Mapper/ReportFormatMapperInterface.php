<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Application\Mapper;

use TasK\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;

/**
 * Контракт маппера формата отчёта.
 */
interface ReportFormatMapperInterface
{
    public function map(OrchestrateChainResultDto $result, string $chainName, string $task): string;
}
