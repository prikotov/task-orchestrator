<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit;

/**
 * Фабрика для создания audit logger с заданным путём к файлу.
 *
 * Создаёт DynamicLoopAuditLoggerInterface, реализующий Port для dynamic-цикла.
 */
interface DynamicLoopAuditLoggerFactoryInterface
{
    /**
     * Создаёт audit logger, пишущий в указанный JSONL-файл.
     */
    public function create(string $logFilePath): DynamicLoopAuditLoggerInterface;
}
