<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\Release;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\Release\ReleaseVersionResolver;
use TaskOrchestrator\Common\ValueObject\ReleaseVersionVo;

/**
 * Unit-проверка резолва версии релиза {@see ReleaseVersionResolver}.
 *
 * Детерминированная часть — приоритет явного APP_RELEASE_VERSION и трактовка
 * пустого значения как отсутствующего. Composer-кандидаты (package/root pretty
 * version) зависят от runtime окружения, поэтому проверяются только как
 * non-empty fallback (их нормализация — в ReleaseVersionVoTest).
 */
#[CoversClass(ReleaseVersionResolver::class)]
final class ReleaseVersionResolverTest extends TestCase
{
    private ?string $explicitBackup = null;

    protected function setUp(): void
    {
        $this->explicitBackup = $_SERVER['APP_RELEASE_VERSION'] ?? null;
        unset($_SERVER['APP_RELEASE_VERSION']);
    }

    protected function tearDown(): void
    {
        if ($this->explicitBackup !== null) {
            $_SERVER['APP_RELEASE_VERSION'] = $this->explicitBackup;
        } else {
            unset($_SERVER['APP_RELEASE_VERSION']);
        }
    }

    #[Test]
    public function resolvePrefersExplicitReleaseVersion(): void
    {
        $_SERVER['APP_RELEASE_VERSION'] = '9.8.7-rc.1';

        self::assertSame('9.8.7-rc.1', (new ReleaseVersionResolver())->resolve());
    }

    #[Test]
    public function resolveStripsLeadingVFromExplicitReleaseVersion(): void
    {
        $_SERVER['APP_RELEASE_VERSION'] = 'v2.3.4';

        self::assertSame('2.3.4', (new ReleaseVersionResolver())->resolve());
    }

    #[Test]
    public function resolveTreatsEmptyExplicitAsUnset(): void
    {
        $_SERVER['APP_RELEASE_VERSION'] = '';

        $version = (new ReleaseVersionResolver())->resolve();

        self::assertNotSame('', $version);
    }

    #[Test]
    public function resolveRejectsNonReleaseExplicitAndFallsBack(): void
    {
        // dev-main не является точной SemVer — резолвер уходит к Composer-кандидатам
        // (package/root pretty version в этом окружении) и не выдаёт ветку как релиз.
        $_SERVER['APP_RELEASE_VERSION'] = 'dev-main';

        $version = (new ReleaseVersionResolver())->resolve();

        self::assertNotSame('dev-main', $version);
        // Либо найдена точная SemVer среди Composer-кандидатов, либо маркер
        // нерелизной сборки — но никогда не «сырое» Composer-значение.
        self::assertTrue(
            $version === ReleaseVersionVo::NON_RELEASE_MARKER
            || preg_match('/^\d+\.\d+\.\d+/', $version) === 1,
            "Unexpected resolved version: {$version}",
        );
    }

    #[Test]
    public function resolveReturnsNonEmptyVersionWithoutExplicitCandidate(): void
    {
        $version = (new ReleaseVersionResolver())->resolve();

        self::assertNotSame('', $version);
        self::assertNotSame('1.0.0.0', $version);
    }
}
