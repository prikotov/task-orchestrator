<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service;

use DateTimeImmutable;
use Override;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\ClockServiceInterface;

/**
 * Реализация ClockService на системных часах.
 *
 * Возвращает «текущее» время; в тестах подменяется детерминированной реализацией.
 */
final class SystemClockService implements ClockServiceInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
