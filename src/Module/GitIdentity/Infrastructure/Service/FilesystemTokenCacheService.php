<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service;

use DateTimeImmutable;
use JsonException;
use Override;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitIdentityException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\TokenCacheInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;

/**
 * Файловый кеш installation token и installation id.
 *
 * Контракт E/I:
 *   - каталог кеша: права 0700 (создаётся при отсутствии);
 *   - файлы кеша: права 0600 (токены — секрет);
 *   - запись атомарна (temp file + rename) под flock;
 *   - повреждённый/частичный JSON обрабатывается gracefully (cache-miss → null).
 *
 * Источником истины expiry токена является expires_at из самого VO; TTL,
 * переданный в writeInstallationToken(), используется как рекомендация и не
 * переопределяет expires_at.
 *
 * Сообщения исключений не содержат токенов (только пути файлов).
 */
final class FilesystemTokenCacheService implements TokenCacheInterface
{
    private const string TOKEN_SUFFIX = '.token.json';

    private const string INSTALLATION_SUFFIX = '.installation.json';

    public function __construct(private string $cacheDir)
    {
    }

    #[Override]
    public function readInstallationToken(InstallationIdVo $installationId): ?InstallationTokenVo
    {
        $path = $this->tokenPath($installationId);
        $data = $this->readJsonGracefully($path);
        if ($data === null) {
            return null;
        }

        $token = $data['token'] ?? null;
        $expiresAtRaw = $data['expires_at'] ?? null;
        $installationIdRaw = $data['installation_id'] ?? null;
        if (!is_string($token) || !is_string($expiresAtRaw) || !is_int($installationIdRaw)) {
            return null;
        }

        $expiresAt = $this->parseTimestamp($expiresAtRaw);
        if ($expiresAt === null) {
            return null;
        }

        if (time() >= $expiresAt->getTimestamp()) {
            // Токен протух — cache-miss, не возвращаем.
            return null;
        }

        try {
            return new InstallationTokenVo(
                $token,
                $expiresAt,
                new InstallationIdVo($installationIdRaw),
            );
        } catch (GitIdentityException) {
            // Невалидный токен/id → cache-miss, перевыпуск.
            return null;
        }
    }

    #[Override]
    public function writeInstallationToken(InstallationTokenVo $token, int $ttlSeconds): void
    {
        $payload = [
            'token' => $token->getToken(),
            'expires_at' => $token->getExpiresAt()->format(DateTimeImmutable::ATOM),
            'installation_id' => $token->getInstallationId()->getValue(),
            // ttl_seconds сохраняется для диагностики; источником истины остаётся expires_at.
            'ttl_seconds' => $ttlSeconds,
        ];

        $this->atomicWrite($this->tokenPath($token->getInstallationId()), $payload);
    }

    #[Override]
    public function invalidateInstallationToken(InstallationIdVo $installationId): void
    {
        $this->silentUnlink($this->tokenPath($installationId));
    }

    #[Override]
    public function readInstallationId(RepoSlugVo $repoSlug): ?InstallationIdVo
    {
        $path = $this->installationPath($repoSlug);
        $data = $this->readJsonGracefully($path);
        if ($data === null) {
            return null;
        }

        $id = $data['installation_id'] ?? null;
        if (!is_int($id)) {
            return null;
        }

        $expiresAtRaw = $data['expires_at'] ?? null;
        if (is_string($expiresAtRaw)) {
            $expiresAt = $this->parseTimestamp($expiresAtRaw);
            if ($expiresAt === null || time() >= $expiresAt->getTimestamp()) {
                return null;
            }
        }

        try {
            return new InstallationIdVo($id);
        } catch (InvalidConfigurationException) {
            return null;
        }
    }

    #[Override]
    public function writeInstallationId(
        RepoSlugVo $repoSlug,
        InstallationIdVo $installationId,
        ?int $ttlSeconds,
    ): void {
        $payload = [
            'installation_id' => $installationId->getValue(),
        ];
        if ($ttlSeconds !== null) {
            $expiry = (new DateTimeImmutable())->modify(sprintf('+%d seconds', max(0, $ttlSeconds)));
            $payload['expires_at'] = $expiry->format(DateTimeImmutable::ATOM);
        }

        $this->atomicWrite($this->installationPath($repoSlug), $payload);
    }

    #[Override]
    public function invalidateInstallationId(RepoSlugVo $repoSlug): void
    {
        $this->silentUnlink($this->installationPath($repoSlug));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function atomicWrite(string $path, array $payload): void
    {
        $this->ensureCacheDir();

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new GitIdentityException(
                sprintf('Failed to encode cache payload for %s.', $path),
                0,
                $e,
            );
        }

        $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
        $handle = @fopen($tmp, 'wb');
        if ($handle === false) {
            throw new GitIdentityException(
                sprintf('Failed to open cache file for writing: %s', $tmp),
            );
        }

        try {
            if (flock($handle, LOCK_EX)) {
                ftruncate($handle, 0);
                fwrite($handle, $json);
                fflush($handle);
                flock($handle, LOCK_UN);
            } else {
                throw new GitIdentityException(
                    sprintf('Failed to acquire lock for cache file: %s', $tmp),
                );
            }
        } finally {
            fclose($handle);
        }

        @chmod($tmp, 0o600);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new GitIdentityException(
                sprintf('Failed to persist cache file: %s', $path),
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJsonGracefully(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return null;
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // Повреждённый/частичный кеш → cache-miss.
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function ensureCacheDir(): void
    {
        if (is_dir($this->cacheDir)) {
            return;
        }
        if (!@mkdir($this->cacheDir, 0o700, true) && !is_dir($this->cacheDir)) {
            throw new InvalidConfigurationException(
                sprintf('Failed to create cache directory: %s', $this->cacheDir),
            );
        }
    }

    private function silentUnlink(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function parseTimestamp(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function tokenPath(InstallationIdVo $installationId): string
    {
        return $this->cacheDir . '/' . $installationId->cacheKey() . self::TOKEN_SUFFIX;
    }

    private function installationPath(RepoSlugVo $repoSlug): string
    {
        return $this->cacheDir . '/' . $repoSlug->cacheKey() . self::INSTALLATION_SUFFIX;
    }
}
