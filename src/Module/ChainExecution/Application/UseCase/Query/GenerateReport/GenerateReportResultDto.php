<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\GenerateReport;

/**
 * Результат генерации отчёта цепочки AI-агентов.
 */
final readonly class GenerateReportResultDto
{
    public function __construct(
        public string $content,
        public string $format,
    ) {
    }
}
