<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\GenerateReport;

use TaskOrchestrator\Common\Module\ChainExecution\Application\Enum\ReportFormatEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;

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
