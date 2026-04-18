<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\AuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\AuditLoggerInterface;
use Override;

/**
 * Фабрика для создания JsonlAuditLogger с заданным путём.
 */
final readonly class JsonlAuditLoggerFactory implements AuditLoggerFactoryInterface
{
    #[Override]
    public function create(string $logFilePath): AuditLoggerInterface
    {
        return new JsonlAuditLogger($logFilePath);
    }
}
