<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Application\UseCase\Query\GenerateReport;

use TasK\Orchestrator\Application\Enum\ReportFormatEnum;
use TasK\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;

/**
 * DTO запроса генерации отчёта по результатам оркестрации.
 */
final readonly class GenerateReportQuery
{
    public function __construct(
        public OrchestrateChainResultDto $result,
        public string $chainName,
        public string $task,
        public ReportFormatEnum $format = ReportFormatEnum::text,
    ) {
    }
}
