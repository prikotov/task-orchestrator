<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\Service;

use DateTimeImmutable;

/**
 * Детерминированный источник времени.
 *
 * Используется во всей логике expiry/TTL, что позволяет подменять время
 * в тестах и делает поведение сравнений предсказуемым.
 */
interface ClockServiceInterface
{
    public function now(): DateTimeImmutable;
}
