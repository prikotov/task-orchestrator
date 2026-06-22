<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\PrivateKeyVo;

#[CoversClass(PrivateKeyVo::class)]
final class PrivateKeyVoTest extends TestCase
{
    private string $pem;

    protected function setUp(): void
    {
        // Throwaway test RSA key (NOT a real secret). Inline embedding triggers gitleaks,
        // so the key lives in fixtures/ which is allowlisted.
        $this->pem = (string) file_get_contents(__DIR__ . '/../../fixtures/test-private-key.pem');
    }

    #[Test]
    public function getContentReturnsPemAsProvided(): void
    {
        $vo = new PrivateKeyVo($this->pem);

        self::assertSame($this->pem, $vo->getContent());
    }

    #[Test]
    public function fingerprintHasSha256PrefixAndHexBody(): void
    {
        $vo = new PrivateKeyVo($this->pem);

        $fingerprint = $vo->fingerprint();

        self::assertStringStartsWith('sha256:', $fingerprint);
        $hex = substr($fingerprint, strlen('sha256:'));
        self::assertSame(16, strlen($hex));
        self::assertSame(16, strlen($hex));
        self::assertSame($hex, strtolower($hex));
        self::assertSame(
            'sha256:' . substr(hash('sha256', $this->pem), 0, 16),
            $fingerprint,
        );
        // Fingerprint must not leak full key material.
        self::assertStringNotContainsString('BEGIN PRIVATE KEY', $fingerprint);
        self::assertStringNotContainsString('MII', $fingerprint);
    }

    #[Test]
    public function fingerprintIsStable(): void
    {
        $a = new PrivateKeyVo($this->pem);
        $b = new PrivateKeyVo($this->pem);

        self::assertSame($a->fingerprint(), $b->fingerprint());
    }

    #[Test]
    public function debugInfoRedactsContent(): void
    {
        $vo = new PrivateKeyVo($this->pem);

        /** @var array<string, string> $debug */
        $debug = $vo->__debugInfo();

        self::assertSame(['content' => '[redacted]'], $debug);
        self::assertStringNotContainsString('MII', var_export($debug, true));
    }

    #[Test]
    public function emptyKeyThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new PrivateKeyVo('');
    }

    #[Test]
    public function nonPemContentThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('does not look like a PEM-encoded key');

        new PrivateKeyVo('just-some-raw-base64-without-pem-markers');
    }

    #[Test]
    public function publicKeyContentThrows(): void
    {
        // BEGIN marker present, but not a PRIVATE KEY — must be rejected.
        $this->expectException(InvalidConfigurationException::class);

        // Non-sensitive placeholder: a PUBLIC key marker with a tiny synthetic payload.
        new PrivateKeyVo("-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A\n-----END PUBLIC KEY-----\n");
    }
}
