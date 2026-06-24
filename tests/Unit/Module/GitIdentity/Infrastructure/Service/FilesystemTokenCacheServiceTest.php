<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Infrastructure\Service;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\FilesystemTokenCacheService;

#[CoversClass(FilesystemTokenCacheService::class)]
final class FilesystemTokenCacheServiceTest extends TestCase
{
    private string $cacheDir;

    private FilesystemTokenCacheService $cache;

    private InstallationIdVo $installationId;

    private RepoSlugVo $repoSlug;

    private string $tokenPath;

    private string $installationPath;

    #[\Override]
    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/git-identity-cache-' . bin2hex(random_bytes(6));
        $this->cache = new FilesystemTokenCacheService($this->cacheDir);
        $this->installationId = new InstallationIdVo(424242);
        $this->repoSlug = RepoSlugVo::createFromString('octocat/Hello-World');
        $this->tokenPath = $this->cacheDir . '/424242.token.json';
        $this->installationPath = $this->cacheDir . '/octocat_Hello-World.installation.json';
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->cacheDir);
    }

    #[Test]
    public function writeAndReadInstallationTokenRoundTrips(): void
    {
        $expiresAt = new DateTimeImmutable('+1 hour');
        $token = new InstallationTokenVo('ghs_cached', $expiresAt, $this->installationId);

        $this->cache->writeInstallationToken($token, 3000);

        $read = $this->cache->readInstallationToken($this->installationId);

        self::assertNotNull($read);
        self::assertSame('ghs_cached', $read->getToken());
        self::assertSame($expiresAt->getTimestamp(), $read->getExpiresAt()->getTimestamp());
        self::assertSame(424242, $read->getInstallationId()->getValue());
    }

    #[Test]
    public function readInstallationTokenReturnsNullWhenMissing(): void
    {
        self::assertNull($this->cache->readInstallationToken($this->installationId));
    }

    #[Test]
    public function readInstallationTokenReturnsNullOnCorruptedJson(): void
    {
        $this->writeRaw($this->tokenPath, '{ this is :: not valid json');

        self::assertNull($this->cache->readInstallationToken($this->installationId));
    }

    #[Test]
    public function readInstallationTokenReturnsNullOnMissingFields(): void
    {
        $this->writeRaw(
            $this->tokenPath,
            json_encode(['token' => 'ghs_partial'], JSON_THROW_ON_ERROR),
        );

        self::assertNull($this->cache->readInstallationToken($this->installationId));
    }

    #[Test]
    public function readInstallationTokenReturnsNullWhenExpired(): void
    {
        // Simulate a cache file written in the past (bypass InstallationTokenVo invariant).
        $this->writeRaw(
            $this->tokenPath,
            json_encode(
                [
                    'token' => 'ghs_old',
                    'expires_at' => (new DateTimeImmutable('-1 hour'))->format(DateTimeImmutable::ATOM),
                    'installation_id' => 424242,
                    'ttl_seconds' => 0,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        self::assertNull($this->cache->readInstallationToken($this->installationId));
    }

    #[Test]
    public function invalidateInstallationTokenRemovesFile(): void
    {
        $token = new InstallationTokenVo('ghs_cached', new DateTimeImmutable('+1 hour'), $this->installationId);
        $this->cache->writeInstallationToken($token, 3000);
        self::assertFileExists($this->tokenPath);

        $this->cache->invalidateInstallationToken($this->installationId);

        self::assertFileDoesNotExist($this->tokenPath);
        self::assertNull($this->cache->readInstallationToken($this->installationId));
    }

    #[Test]
    public function invalidateInstallationTokenOnMissingFileIsNoop(): void
    {
        $this->cache->invalidateInstallationToken($this->installationId);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function writeAndReadInstallationIdRoundTrips(): void
    {
        $this->cache->writeInstallationId($this->repoSlug, $this->installationId, 86400);

        $read = $this->cache->readInstallationId($this->repoSlug);

        self::assertNotNull($read);
        self::assertSame(424242, $read->getValue());
    }

    #[Test]
    public function installationIdWithNullTtlNeverExpires(): void
    {
        $this->cache->writeInstallationId($this->repoSlug, $this->installationId, null);

        self::assertNotNull($this->cache->readInstallationId($this->repoSlug));
    }

    #[Test]
    public function installationIdWithTtlReturnsNullWhenExpired(): void
    {
        // Simulate an installation id cache entry that has already expired.
        $this->writeRaw(
            $this->installationPath,
            json_encode(
                [
                    'installation_id' => 424242,
                    'expires_at' => (new DateTimeImmutable('-1 hour'))->format(DateTimeImmutable::ATOM),
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        self::assertNull($this->cache->readInstallationId($this->repoSlug));
    }

    #[Test]
    public function installationIdWithCorruptedJsonReturnsNull(): void
    {
        $this->writeRaw($this->installationPath, '###broken');

        self::assertNull($this->cache->readInstallationId($this->repoSlug));
    }

    #[Test]
    public function invalidateInstallationIdRemovesFile(): void
    {
        $this->cache->writeInstallationId($this->repoSlug, $this->installationId, null);
        self::assertFileExists($this->installationPath);

        $this->cache->invalidateInstallationId($this->repoSlug);

        self::assertFileDoesNotExist($this->installationPath);
        self::assertNull($this->cache->readInstallationId($this->repoSlug));
    }

    #[Test]
    public function tokenCacheFileHasMode0600AndDirectory0700(): void
    {
        $token = new InstallationTokenVo('ghs_cached', new DateTimeImmutable('+1 hour'), $this->installationId);
        $this->cache->writeInstallationToken($token, 3000);

        self::assertFileExists($this->tokenPath);
        $tokenPerms = fileperms($this->tokenPath);
        $dirPerms = fileperms($this->cacheDir);
        self::assertNotFalse($tokenPerms, 'token file permissions must be readable');
        self::assertNotFalse($dirPerms, 'cache directory permissions must be readable');
        self::assertSame(0o600, $tokenPerms & 0o777, 'token cache file must be 0600');
        self::assertSame(0o700, $dirPerms & 0o777, 'cache directory must be 0700');
    }

    #[Test]
    public function writeDoesNotLeaveTempFilesBehind(): void
    {
        $token = new InstallationTokenVo('ghs_cached', new DateTimeImmutable('+1 hour'), $this->installationId);

        $this->cache->writeInstallationToken($token, 3000);

        $leftovers = glob($this->cacheDir . '/*.tmp.*');
        self::assertSame([], $leftovers, 'atomic write must not leave .tmp files');
    }

    #[Test]
    public function tokenPayloadNeverAppearsInExceptionMessages(): void
    {
        // Point the cache at a path under a read-only directory to force a write failure
        // while keeping the parent path predictable.
        $readOnlyDir = sys_get_temp_dir() . '/git-identity-ro-' . bin2hex(random_bytes(6));
        mkdir($readOnlyDir, 0o500, true);
        $denied = $readOnlyDir . '/nested';

        try {
            $service = new FilesystemTokenCacheService($denied);
            $token = new InstallationTokenVo('ghs_secret', new DateTimeImmutable('+1 hour'), $this->installationId);

            try {
                $service->writeInstallationToken($token, 3000);
                self::fail('Expected cache write to fail under read-only parent.');
            } catch (\Throwable $e) {
                self::assertStringNotContainsString('ghs_secret', $e->getMessage());
            }
        } finally {
            @chmod($readOnlyDir, 0o700);
            $this->removeDirectory($readOnlyDir);
        }
    }

    private function writeRaw(string $path, string $content): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0o700, true);
        }
        file_put_contents($path, $content);
        chmod($path, 0o600);
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
