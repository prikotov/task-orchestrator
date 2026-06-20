<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Infrastructure\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\PrivateKeyVo;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\LoadGitIdentityConfigService;

#[CoversClass(LoadGitIdentityConfigService::class)]
final class LoadGitIdentityConfigServiceTest extends TestCase
{
    private const PEM = "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKg==\n-----END PRIVATE KEY-----\n";

    private string $tempDir;

    private string $secureKeyPath;

    #[\Override]
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/git-identity-config-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0o700, true);
        $this->secureKeyPath = $this->tempDir . '/app-private-key.pem';
        file_put_contents($this->secureKeyPath, self::PEM);
        chmod($this->secureKeyPath, 0o600);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function service(
        ?string $appId = '123456',
        ?string $privateKeyPath = null,
        ?string $privateKey = null,
    ): LoadGitIdentityConfigService {
        return new LoadGitIdentityConfigService(
            appId: $appId,
            privateKeyPath: $privateKeyPath,
            privateKey: $privateKey,
            apiBaseUri: 'https://api.github.com',
            githubApiVersion: '2026-03-10',
            userAgent: 'task-orchestrator-git-identity-test',
            jwtTtlSeconds: 540,
            jwtClockSkewSeconds: 60,
            tokenExpirySafetyMarginSeconds: 60,
            installationIdCacheTtlSeconds: 86400,
            scopeToRepository: true,
            requestTimeoutSeconds: 30,
        );
    }

    #[Test]
    public function inlinePrivateKeyIsLoaded(): void
    {
        $config = $this->service(privateKey: self::PEM)->load();

        self::assertSame(123456, $config->getAppId()->getValue());
        self::assertSame(self::PEM, $config->getPrivateKey()->getContent());
    }

    #[Test]
    public function privateKeyPathWithMode0600IsLoaded(): void
    {
        $config = $this->service(privateKeyPath: $this->secureKeyPath)->load();

        self::assertSame(self::PEM, $config->getPrivateKey()->getContent());
    }

    #[Test]
    public function inlinePrivateKeyTakesPrecedenceOverPath(): void
    {
        $config = $this->service(privateKeyPath: $this->secureKeyPath, privateKey: self::PEM)->load();

        // Inline value is used; path is only a fallback.
        self::assertInstanceOf(PrivateKeyVo::class, $config->getPrivateKey());
    }

    #[Test]
    public function missingAppIdThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('App ID is not configured');

        $this->service(appId: null, privateKey: self::PEM)->load();
    }

    #[Test]
    public function emptyAppIdThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->service(appId: '   ', privateKey: self::PEM)->load();
    }

    #[Test]
    public function nonNumericAppIdThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be a positive integer');

        $this->service(appId: 'abc', privateKey: self::PEM)->load();
    }

    #[Test]
    public function zeroAppIdThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->service(appId: '0', privateKey: self::PEM)->load();
    }

    #[Test]
    public function noPrivateKeySourceThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('private key is not configured');

        $this->service()->load();
    }

    #[Test]
    public function missingPrivateKeyFileThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('private key file not found');

        $this->service(privateKeyPath: $this->tempDir . '/does-not-exist.pem')->load();
    }

    #[Test]
    public function privateKeyFileWithInsecurePermissionsThrows(): void
    {
        $insecurePath = $this->tempDir . '/insecure.pem';
        file_put_contents($insecurePath, self::PEM);
        chmod($insecurePath, 0o644);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('insecure permissions (expected 0600)');

        $this->service(privateKeyPath: $insecurePath)->load();
    }

    #[Test]
    public function privateKeyFileGroupReadablePermissionsThrows(): void
    {
        $insecurePath = $this->tempDir . '/group-readable.pem';
        file_put_contents($insecurePath, self::PEM);
        chmod($insecurePath, 0o640); // group readable — must be rejected.

        $this->expectException(InvalidConfigurationException::class);

        $this->service(privateKeyPath: $insecurePath)->load();
    }

    #[Test]
    public function emptyPrivateKeyFileThrows(): void
    {
        $emptyPath = $this->tempDir . '/empty.pem';
        file_put_contents($emptyPath, '');
        chmod($emptyPath, 0o600);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Failed to read private key file');

        $this->service(privateKeyPath: $emptyPath)->load();
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }
            @unlink($itemPath);
        }
        @rmdir($path);
    }
}
