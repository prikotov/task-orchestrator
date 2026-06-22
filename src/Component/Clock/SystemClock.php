<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\Clock;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * Реализация PSR-20 Clock на системных часах.
 *
 * Возвращает «текущее» время; в тестах подменяется детерминированной
 * реализацией (Fake/Stub), реализующей {@see ClockInterface}.
 *
 * Универсальный контракт источника времени (PSR-20) не принадлежит конкретному
 * модулю, поэтому реализация вынесена в общий Component и доступна всем модулям.
 *
 * @see ClockInterface
 */
final class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
