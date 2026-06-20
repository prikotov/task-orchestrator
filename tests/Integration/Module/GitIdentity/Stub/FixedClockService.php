<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\GitIdentity\Stub;

use DateTimeImmutable;
use Override;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\ClockServiceInterface;

/**
 * Детерминированные часы для интеграционных тестов: всегда возвращают
 * фиксированный момент времени.
 */
final class FixedClockService implements ClockServiceInterface
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
