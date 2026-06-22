<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Infrastructure\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\PrivateKeyVo;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\LoadGitIdentityConfigService;

/**
 * Тесты {@see LoadGitIdentityConfigService}: чтение конфигурации из env
 * (putenv в setUp, очистка в tearDown).
 *
 * Все AGENT_* env-переменные, которых касается сервис, перечислены в
 * {@see ENV_VARS} и гарантированно сбрасываются в tearDown, чтобы тесты
 * оставались детерминированными и не помечались PHPUnit как risky
 * (манипуляция глобальным состоянием).
 */
#[CoversClass(LoadGitIdentityConfigService::class)]
final class LoadGitIdentityConfigServiceTest extends TestCase
{
    private const PEM = "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKg==\n-----END PRIVATE KEY-----\n";

    /** Все env-переменные, читаемые сервисом — для полной очистки в tearDown. */
    private const array ENV_VARS = [
        'AGENT_APP_ID',
        'AGENT_PRIVATE_KEY',
        'AGENT_PRIVATE_KEY_PATH',
        'AGENT_API_BASE_URI',
        'AGENT_GITHUB_API_VERSION',
        'AGENT_USER_AGENT',
        'AGENT_JWT_TTL_SECONDS',
        'AGENT_JWT_CLOCK_SKEW_SECONDS',
        'AGENT_TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS',
        'AGENT_INSTALLATION_ID_CACHE_TTL_SECONDS',
        'AGENT_SCOPE_TO_REPOSITORY',
        'AGENT_REQUEST_TIMEOUT_SECONDS',
    ];

    private string $tempDir;

    private string $secureKeyPath;

    #[\Override]
    protected function setUp(): void
    {
        // Гарантируем чистое окружение на старте каждого теста.
        $this->clearEnv();

        $this->tempDir = sys_get_temp_dir() . '/git-identity-config-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0o700, true);
        $this->secureKeyPath = $this->tempDir . '/app-private-key.pem';
        file_put_contents($this->secureKeyPath, self::PEM);
        chmod($this->secureKeyPath, 0o600);

        // Минимальный валидный набор: обязательный app id + inline-ключ.
        putenv('AGENT_APP_ID=123456');
        putenv('AGENT_PRIVATE_KEY=' . self::PEM);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->clearEnv();
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function defaultsAreAppliedWhenOptionalEnvIsAbsent(): void
    {
        $config = (new LoadGitIdentityConfigService())->load();

        self::assertSame(123456, $config->getAppId()->getValue());
        self::assertSame('https://api.github.com', $config->getApiBaseUri());
        self::assertSame('2022-11-28', $config->getGitHubApiVersion());
        self::assertSame('task-orchestrator-git-identity', $config->getUserAgent());
        self::assertSame(540, $config->getJwtTtlSeconds());
        self::assertSame(60, $config->getJwtClockSkewSeconds());
        self::assertSame(60, $config->getTokenExpirySafetyMarginSeconds());
        self::assertSame(86400, $config->getInstallationIdCacheTtlSeconds());
        self::assertTrue($config->shouldScopeToRepository());
        self::assertSame(30, $config->getRequestTimeoutSeconds());
    }

    #[Test]
    public function optionalEnvOverridesDefaults(): void
    {
        putenv('AGENT_API_BASE_URI=https://github.enterprise.local/api/v3');
        putenv('AGENT_GITHUB_API_VERSION=2022-11-28');
        putenv('AGENT_USER_AGENT=custom-agent');
        putenv('AGENT_JWT_TTL_SECONDS=600');
        putenv('AGENT_JWT_CLOCK_SKEW_SECONDS=10');
        putenv('AGENT_TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS=15');
        putenv('AGENT_INSTALLATION_ID_CACHE_TTL_SECONDS=3600');
        putenv('AGENT_SCOPE_TO_REPOSITORY=false');
        putenv('AGENT_REQUEST_TIMEOUT_SECONDS=45');

        $config = (new LoadGitIdentityConfigService())->load();

        self::assertSame('https://github.enterprise.local/api/v3', $config->getApiBaseUri());
        self::assertSame('custom-agent', $config->getUserAgent());
        self::assertSame(600, $config->getJwtTtlSeconds());
        self::assertSame(10, $config->getJwtClockSkewSeconds());
        self::assertSame(15, $config->getTokenExpirySafetyMarginSeconds());
        self::assertSame(3600, $config->getInstallationIdCacheTtlSeconds());
        self::assertFalse($config->shouldScopeToRepository());
        self::assertSame(45, $config->getRequestTimeoutSeconds());
    }

    #[Test]
    public function installationIdCacheTtlEnvNullMeansNoExpiry(): void
    {
        putenv('AGENT_INSTALLATION_ID_CACHE_TTL_SECONDS=null');

        $config = (new LoadGitIdentityConfigService())->load();

        self::assertNull($config->getInstallationIdCacheTtlSeconds());
    }

    #[Test]
    public function inlinePrivateKeyIsLoaded(): void
    {
        $config = (new LoadGitIdentityConfigService())->load();

        self::assertSame(123456, $config->getAppId()->getValue());
        self::assertSame(self::PEM, $config->getPrivateKey()->getContent());
    }

    #[Test]
    public function privateKeyPathWithMode0600IsLoaded(): void
    {
        putenv('AGENT_PRIVATE_KEY'); // убираем inline-ключ
        putenv('AGENT_PRIVATE_KEY_PATH=' . $this->secureKeyPath);

        $config = (new LoadGitIdentityConfigService())->load();

        self::assertSame(self::PEM, $config->getPrivateKey()->getContent());
    }

    #[Test]
    public function inlinePrivateKeyTakesPrecedenceOverPath(): void
    {
        // Оба источника заданы — inline приоритетнее, файл не требуется.
        putenv('AGENT_PRIVATE_KEY_PATH=' . $this->secureKeyPath);

        $config = (new LoadGitIdentityConfigService())->load();

        self::assertInstanceOf(PrivateKeyVo::class, $config->getPrivateKey());
        self::assertSame(self::PEM, $config->getPrivateKey()->getContent());
    }

    #[Test]
    public function missingAppIdThrows(): void
    {
        putenv('AGENT_APP_ID');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('App ID is not configured');

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function emptyAppIdThrows(): void
    {
        putenv('AGENT_APP_ID=   ');

        $this->expectException(InvalidConfigurationException::class);

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function nonNumericAppIdThrows(): void
    {
        putenv('AGENT_APP_ID=abc');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be a positive integer');

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function zeroAppIdThrows(): void
    {
        putenv('AGENT_APP_ID=0');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be a positive integer');

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function noPrivateKeySourceThrows(): void
    {
        putenv('AGENT_PRIVATE_KEY');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('private key is not configured');

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function missingPrivateKeyFileThrows(): void
    {
        putenv('AGENT_PRIVATE_KEY');
        putenv('AGENT_PRIVATE_KEY_PATH=' . $this->tempDir . '/does-not-exist.pem');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('private key file not found');

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function privateKeyFileWithInsecurePermissionsThrows(): void
    {
        putenv('AGENT_PRIVATE_KEY');
        $insecurePath = $this->tempDir . '/insecure.pem';
        file_put_contents($insecurePath, self::PEM);
        chmod($insecurePath, 0o644);
        putenv('AGENT_PRIVATE_KEY_PATH=' . $insecurePath);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('insecure permissions (expected 0600)');

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function privateKeyFileGroupReadablePermissionsThrows(): void
    {
        putenv('AGENT_PRIVATE_KEY');
        $insecurePath = $this->tempDir . '/group-readable.pem';
        file_put_contents($insecurePath, self::PEM);
        chmod($insecurePath, 0o640); // group readable — must be rejected.
        putenv('AGENT_PRIVATE_KEY_PATH=' . $insecurePath);

        $this->expectException(InvalidConfigurationException::class);

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function emptyPrivateKeyFileThrows(): void
    {
        putenv('AGENT_PRIVATE_KEY');
        $emptyPath = $this->tempDir . '/empty.pem';
        file_put_contents($emptyPath, '');
        chmod($emptyPath, 0o600);
        putenv('AGENT_PRIVATE_KEY_PATH=' . $emptyPath);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Failed to read private key file');

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function nonNumericJwtTtlThrows(): void
    {
        putenv('AGENT_JWT_TTL_SECONDS=not-a-number');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('AGENT_JWT_TTL_SECONDS must be an integer');

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function jwtTtlOutOfRangeIsRejectedByVo(): void
    {
        putenv('AGENT_JWT_TTL_SECONDS=99999');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('jwt_ttl_seconds must be in range 1..600');

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function invalidBooleanThrows(): void
    {
        putenv('AGENT_SCOPE_TO_REPOSITORY=maybe');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('AGENT_SCOPE_TO_REPOSITORY must be a boolean');

        (new LoadGitIdentityConfigService())->load();
    }

    #[Test]
    public function invalidApiBaseUriIsRejectedByVo(): void
    {
        putenv('AGENT_API_BASE_URI=not-a-url');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid GitHub API base URI');

        (new LoadGitIdentityConfigService())->load();
    }

    private function clearEnv(): void
    {
        foreach (self::ENV_VARS as $name) {
            putenv($name);
        }
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
