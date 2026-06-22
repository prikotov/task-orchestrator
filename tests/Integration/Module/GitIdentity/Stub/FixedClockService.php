<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\GitIdentity\Stub;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * Детерминированные часы для интеграционных тестов: всегда возвращают
 * фиксированный момент времени.
 */
final class FixedClockService implements ClockInterface
{
    public function __construct(
        private readonly DateTimeImmutable $now,
    ) {
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
