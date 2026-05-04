<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain;

use Override;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Audit\AuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Audit\AuditLoggerInterface;

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
