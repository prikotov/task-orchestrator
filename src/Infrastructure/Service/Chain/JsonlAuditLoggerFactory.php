<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Infrastructure\Service\Chain;

use TasK\Orchestrator\Domain\Service\Chain\AuditLoggerFactoryInterface;
use TasK\Orchestrator\Domain\Service\Chain\AuditLoggerInterface;
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
