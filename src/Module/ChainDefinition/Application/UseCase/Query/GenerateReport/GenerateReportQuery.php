<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\GenerateReport;

use TaskOrchestrator\Common\Module\ChainDefinition\Application\Enum\ReportFormatEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;

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
