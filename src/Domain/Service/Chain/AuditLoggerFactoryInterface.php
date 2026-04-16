<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

/**
 * Фабрика для создания audit logger с заданным путём к файлу.
 *
 * Используется в Application-слое для создания logger'а
 * с runtime-путём (из CLI-опции --audit-log).
 */
interface AuditLoggerFactoryInterface
{
    /**
     * Создаёт audit logger, пишущий в указанный JSONL-файл.
     */
    public function create(string $logFilePath): AuditLoggerInterface;
}
