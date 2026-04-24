<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport;

/**
 * Интерфейс обработчика запроса генерации отчёта.
 *
 * Позволяет Presentation-слою зависеть от абстракции (DIP),
 * а не от конкретного final readonly handler'а.
 */
interface GenerateReportHandlerInterface
{
    public function __invoke(GenerateReportQuery $query): GenerateReportResultDto;
}
