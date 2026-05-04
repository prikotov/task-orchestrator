<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service;

use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;

/**
 * Фабрика для создания JsonlAuditLogger с заданным путём.
 *
 * Создаёт логгер, реализующий оба Port'а:
 * - AuditLoggerInterface (ChainDefinition)
 * - DynamicLoopAuditLoggerInterface (DynamicLoop)
 */
final readonly class JsonlAuditLoggerFactory implements DynamicLoopAuditLoggerFactoryInterface
{
    #[Override]
    public function create(string $logFilePath): DynamicLoopAuditLoggerInterface
    {
        return new JsonlAuditLogger($logFilePath);
    }
}
