<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Application\UseCase\Query\GenerateReport;

use TasK\Orchestrator\Application\UseCase\Query\GenerateReport\ReportResultFactory;

/**
 * UseCase генерации отчёта по результатам оркестрации цепочки AI-агентов.
 */
final readonly class GenerateReportQueryHandler
{
    public function __construct(
        private readonly ReportResultFactory $reportResultFactory,
    ) {
    }

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
