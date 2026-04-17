<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Enum;

/**
 * Формат отчёта о выполнении цепочки AI-агентов.
 */
enum ReportFormatEnum: string
{
    case text = 'text';
    case json = 'json';
}
