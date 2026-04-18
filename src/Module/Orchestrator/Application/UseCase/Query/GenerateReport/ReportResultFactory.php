<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Enum\ReportFormatEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper\ReportFormatMapperInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;

/**
 * Фабрика создания результата генерации отчёта.
 *
 * @param array<string, ReportFormatMapperInterface> $mappers
 */
final readonly class ReportResultFactory
{
    /**
     * @param array<string, ReportFormatMapperInterface> $mappers
     */
    public function __construct(
        private readonly array $mappers,
    ) {
    }

    public function create(
        OrchestrateChainResultDto $result,
        string $chainName,
        string $task,
        ReportFormatEnum $format,
    ): GenerateReportResultDto {
        $mapper = $this->mappers[$format->value]
            ?? throw new \InvalidArgumentException(
                sprintf('No mapper registered for report format "%s".', $format->value),
            );

        return new GenerateReportResultDto(
            content: $mapper->map($result, $chainName, $task),
            format: $format->value,
        );
    }
}
