<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Domain\ValueObject;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;

#[CoversClass(JwtTokenVo::class)]
final class JwtTokenVoTest extends TestCase
{
    #[Test]
    public function getValueReturnsProvidedToken(): void
    {
        $expiresAt = new DateTimeImmutable('+10 minutes');
        $vo = new JwtTokenVo('header.payload.signature', $expiresAt);

        self::assertSame('header.payload.signature', $vo->getValue());
        self::assertSame($expiresAt, $vo->getExpiresAt());
    }

    #[Test]
    public function isExpiredAtReturnsFalseBeforeExpiry(): void
    {
        $expiresAt = new DateTimeImmutable('+10 minutes');
        $vo = new JwtTokenVo('jwt', $expiresAt);

        self::assertFalse($vo->isExpiredAt(new DateTimeImmutable('now')));
    }

    #[Test]
    public function isExpiredAtReturnsTrueAtExpiry(): void
    {
        $expiresAt = new DateTimeImmutable('+10 minutes');
        $vo = new JwtTokenVo('jwt', $expiresAt);

        self::assertTrue($vo->isExpiredAt($expiresAt));
    }

    #[Test]
    public function isExpiredAtReturnsTrueAfterExpiry(): void
    {
        $expiresAt = new DateTimeImmutable('+1 minute');
        $vo = new JwtTokenVo('jwt', $expiresAt);

        self::assertFalse($vo->isExpiredAt(new DateTimeImmutable('now')));
        self::assertTrue($vo->isExpiredAt($expiresAt->modify('+1 second')));
    }

    #[Test]
    public function emptyTokenThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new JwtTokenVo('', new DateTimeImmutable('+10 minutes'));
    }

    #[Test]
    public function pastExpiryIsAllowedAndDetectedViaIsExpiredAt(): void
    {
        // VO больше не читает системные часы в конструкторе (чистый детерминированный VO):
        // past expiry не бросается; его определяет Application через isExpiredAt().
        $expiresAt = new DateTimeImmutable('-1 minute');
        $vo = new JwtTokenVo('jwt', $expiresAt);

        self::assertSame($expiresAt, $vo->getExpiresAt());
        self::assertTrue($vo->isExpiredAt(new DateTimeImmutable('now')));
    }

    #[Test]
    public function debugInfoRedactsValue(): void
    {
        $vo = new JwtTokenVo('very.secret.jwt.value', new DateTimeImmutable('+10 minutes'));

        /** @var array<string, string> $debug */
        $debug = $vo->__debugInfo();

        self::assertSame(['value' => '[redacted]'], $debug);
        self::assertStringNotContainsString('very.secret.jwt.value', var_export($debug, true));
    }
}
