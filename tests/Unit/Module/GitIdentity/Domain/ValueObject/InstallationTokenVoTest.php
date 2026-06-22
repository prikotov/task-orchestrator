<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Domain\ValueObject;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitIdentityException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationTokenVo;

#[CoversClass(InstallationTokenVo::class)]
final class InstallationTokenVoTest extends TestCase
{
    private function installationId(): InstallationIdVo
    {
        return new InstallationIdVo(424242);
    }

    #[Test]
    public function gettersReturnProvidedValues(): void
    {
        $expiresAt = new DateTimeImmutable('+1 hour');
        $id = $this->installationId();
        $vo = new InstallationTokenVo('ghs_secret_token', $expiresAt, $id);

        self::assertSame('ghs_secret_token', $vo->getToken());
        self::assertSame($expiresAt, $vo->getExpiresAt());
        self::assertTrue($vo->getInstallationId()->equals($id));
    }

    #[Test]
    public function isUsableAtReturnsTrueWithSafetyMarginRemaining(): void
    {
        $now = new DateTimeImmutable();
        $expiresAt = $now->modify('+3600 seconds');
        $vo = new InstallationTokenVo('ghs_token', $expiresAt, $this->installationId());

        self::assertTrue($vo->isUsableAt($now, 60));
    }

    #[Test]
    public function isUsableAtReturnsFalseWithinSafetyMargin(): void
    {
        // expiry in 30s, margin 60s → unusable (within margin window).
        $now = new DateTimeImmutable();
        $expiresAt = $now->modify('+30 seconds');
        $vo = new InstallationTokenVo('ghs_token', $expiresAt, $this->installationId());

        self::assertFalse($vo->isUsableAt($now, 60));
    }

    #[Test]
    public function isUsableAtWithZeroMarginUsesExactExpiry(): void
    {
        $now = new DateTimeImmutable();
        $expiresAt = $now->modify('+1 second');
        $vo = new InstallationTokenVo('ghs_token', $expiresAt, $this->installationId());

        self::assertTrue($vo->isUsableAt($now, 0));
    }

    #[Test]
    public function cacheTtlSecondsIsExpiryMinusNowMinusMargin(): void
    {
        $now = new DateTimeImmutable();
        $expiresAt = $now->modify('+3600 seconds');
        $vo = new InstallationTokenVo('ghs_token', $expiresAt, $this->installationId());

        self::assertSame(3540, $vo->cacheTtlSeconds($now, 60));
    }

    #[Test]
    public function cacheTtlSecondsNeverNegative(): void
    {
        $now = new DateTimeImmutable();
        // Margin larger than remaining lifetime, but expiry still in the future.
        $expiresAt = $now->modify('+10 seconds');
        $vo = new InstallationTokenVo('ghs_token', $expiresAt, $this->installationId());

        self::assertSame(0, $vo->cacheTtlSeconds($now, 60));
    }

    #[Test]
    public function emptyTokenThrowsGitHubApiException(): void
    {
        $this->expectException(GitHubApiException::class);
        $this->expectException(GitIdentityException::class);

        new InstallationTokenVo('', new DateTimeImmutable('+1 hour'), $this->installationId());
    }

    #[Test]
    public function pastExpiryIsAllowedAndDetectedViaIsUsableAt(): void
    {
        // VO больше не читает системные часы в конструкторе (чистый детерминированный VO):
        // past expiry не бросается; пригодность определяет Application через isUsableAt().
        $now = new DateTimeImmutable();
        $expiresAt = $now->modify('-1 minute');
        $vo = new InstallationTokenVo('ghs_token', $expiresAt, $this->installationId());

        self::assertSame($expiresAt, $vo->getExpiresAt());
        self::assertFalse($vo->isUsableAt($now, 0));
    }

    #[Test]
    public function debugInfoRedactsTokenButKeepsExpiryAndInstallationId(): void
    {
        $expiresAt = new DateTimeImmutable('+1 hour');
        $vo = new InstallationTokenVo('ghs_top_secret', $expiresAt, new InstallationIdVo(999));

        /** @var array<string, string|int> $debug */
        $debug = $vo->__debugInfo();

        self::assertSame('[redacted]', $debug['token']);
        self::assertSame($expiresAt->format(DateTimeImmutable::ATOM), $debug['expiresAt']);
        self::assertSame(999, $debug['installationId']);
        self::assertStringNotContainsString('ghs_top_secret', var_export($debug, true));
    }
}
