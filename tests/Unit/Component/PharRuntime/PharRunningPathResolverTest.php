<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\PharRuntime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\PharRuntime\PharRunningPathResolver;

/**
 * Unit-проверка {@see PharRunningPathResolver}: единственной точки обращения
 * к static API \Phar в src/.
 *
 * В обычном CLI-окружении (исполнение вне PHAR-архива) Phar::running(false)
 * возвращает пустую строку, а без расширения phar класс \Phar недоступен —
 * оба случая обязаны давать null (потребители трактуют null как «вне архива»).
 * Сценарий реального архива покрывается integration-уровнем (Kernel с
 * protected-шовом resolvePharPath) и `make phar-smoke`.
 */
#[CoversClass(PharRunningPathResolver::class)]
final class PharRunningPathResolverTest extends TestCase
{
    #[Test]
    public function returnsNullOutsidePharArchive(): void
    {
        self::assertNull((new PharRunningPathResolver())->runningPath());
    }
}
