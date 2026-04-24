<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\ReportResultFactory;

/**
 * UseCase генерации отчёта по результатам оркестрации цепочки AI-агентов.
 */
final readonly class GenerateReportQueryHandler implements GenerateReportHandlerInterface
{
    public function __construct(
        private readonly ReportResultFactory $reportResultFactory,
    ) {
    }

    #[Override]
    public function __invoke(GenerateReportQuery $query): GenerateReportResultDto
    {
        return $this->reportResultFactory->create(
            $query->result,
            $query->chainName,
            $query->task,
            $query->format,
        );
    }
}
