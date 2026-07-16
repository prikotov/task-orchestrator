<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\ValueObject\ReleaseVersionVo;

/**
 * Unit-проверка чистого механизма разрешения релизной версии {@see ReleaseVersionVo}.
 *
 * Покрывает приоритет источников (explicit → package → root), валидацию точной
 * SemVer, удаление префикса `v`, допустимость prerelease/build и недопустимость
 * нормализованных Composer-значений (`1.0.0.0`, `dev-main`,
 * `1.0.0+no-version-set`) как релизной версии.
 */
#[CoversClass(ReleaseVersionVo::class)]
final class ReleaseVersionVoTest extends TestCase
{
    #[Test]
    #[DataProvider('validSemVerCandidates')]
    public function resolveAcceptsExactSemVerCandidate(string $candidate, string $expected): void
    {
        // Arrange

        // Act
        $version = ReleaseVersionVo::createFromCandidates(
            explicitReleaseVersion: $candidate,
            packagePrettyVersion: null,
            rootPrettyVersion: null,
        );

        // Assert
        self::assertSame($expected, $version->value());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validSemVerCandidates(): iterable
    {
        yield 'plain patch' => ['0.2.1', '0.2.1'];
        yield 'leading v lowercased' => ['v0.2.1', '0.2.1'];
        yield 'prerelease' => ['0.2.1-rc.1', '0.2.1-rc.1'];
        yield 'prerelease with leading v' => ['v1.0.0-alpha.2+build.5', '1.0.0-alpha.2+build.5'];
        yield 'build metadata' => ['1.0.0+exp.sha.5114f85', '1.0.0+exp.sha.5114f85'];
        yield 'zero version' => ['0.0.0', '0.0.0'];
    }

    #[Test]
    #[DataProvider('invalidCandidates')]
    public function resolveRejectsNonReleaseValueAndFallsBackToDevMarker(?string $candidate): void
    {
        // Arrange

        // Act
        $version = ReleaseVersionVo::createFromCandidates(
            explicitReleaseVersion: $candidate,
            packagePrettyVersion: null,
            rootPrettyVersion: null,
        );

        // Assert
        self::assertSame(ReleaseVersionVo::NON_RELEASE_MARKER, $version->value());
        self::assertSame('dev', $version->value());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function invalidCandidates(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'normalized four-part composer version' => ['1.0.0.0'];
        yield 'dev branch' => ['dev-main'];
        yield 'composer no-version-set marker' => ['1.0.0+no-version-set'];
        yield 'two-part version' => ['1.0'];
        yield 'major only' => ['1'];
        yield 'garbage' => ['not-a-version'];
        yield 'leading v with four-part' => ['v1.0.0.0'];
        yield 'leading V uppercased' => ['V1.2.3'];
        yield 'major leading zero' => ['01.2.3'];
        yield 'minor leading zero' => ['1.02.3'];
        yield 'patch leading zero' => ['1.2.03'];
        yield 'numeric prerelease leading zero' => ['1.2.3-01'];
        yield 'empty prerelease identifier' => ['1.2.3-alpha..1'];
        yield 'empty build identifier' => ['1.2.3+build..1'];
    }

    #[Test]
    public function resolvePrioritizesExplicitOverPackageAndRoot(): void
    {
        // Arrange

        // Act
        $version = ReleaseVersionVo::createFromCandidates(
            explicitReleaseVersion: '0.2.1',
            packagePrettyVersion: '0.2.0',
            rootPrettyVersion: '0.1.0',
        );

        // Assert
        self::assertSame('0.2.1', $version->value());
    }

    #[Test]
    public function resolvePrioritizesPackageOverRootForComposerDistribution(): void
    {
        // Arrange

        // Act
        $version = ReleaseVersionVo::createFromCandidates(
            explicitReleaseVersion: null,
            packagePrettyVersion: '0.2.1',
            rootPrettyVersion: 'dev-main',
        );

        // Assert
        self::assertSame('0.2.1', $version->value());
    }

    #[Test]
    public function resolveUsesRootPrettyWhenPackageIsAbsent(): void
    {
        // Arrange

        // Act
        $version = ReleaseVersionVo::createFromCandidates(
            explicitReleaseVersion: null,
            packagePrettyVersion: null,
            rootPrettyVersion: 'v0.2.1',
        );

        // Assert
        self::assertSame('0.2.1', $version->value());
    }

    #[Test]
    public function resolveFallsBackToDevWhenNoCandidateIsExactSemVer(): void
    {
        // Arrange: root checkout без релизной версии.

        // Act
        $version = ReleaseVersionVo::createFromCandidates(
            explicitReleaseVersion: null,
            packagePrettyVersion: 'dev-main',
            rootPrettyVersion: '1.0.0+no-version-set',
        );

        // Assert
        self::assertSame('dev', $version->value());
    }

    #[Test]
    public function resolveRejectsNormalizedVersionFromRootPackage(): void
    {
        // Arrange: нормализованное Composer-значение `1.0.0.0` (дефект PHAR v0.2.0)
        // не должно проходить как релизная версия ни из одного источника.

        // Act
        $version = ReleaseVersionVo::createFromCandidates(
            explicitReleaseVersion: '1.0.0.0',
            packagePrettyVersion: '1.0.0.0',
            rootPrettyVersion: '1.0.0.0',
        );

        // Assert
        self::assertSame('dev', $version->value());
    }

    #[Test]
    public function resolveSkipsInvalidExplicitAndUsesValidPackage(): void
    {
        // Arrange: некорректный explicit не должен блокировать валидный package.

        // Act
        $version = ReleaseVersionVo::createFromCandidates(
            explicitReleaseVersion: 'dev-main',
            packagePrettyVersion: '0.2.1',
            rootPrettyVersion: null,
        );

        // Assert
        self::assertSame('0.2.1', $version->value());
    }
}
