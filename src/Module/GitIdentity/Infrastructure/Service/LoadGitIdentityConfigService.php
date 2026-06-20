<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service;

use Override;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\LoadGitIdentityConfigServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\AppIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\PrivateKeyVo;

/**
 * Загрузчик конфигурации GitIdentity.
 *
 * Источники (env уже загружены bin/console через Symfony Dotenv — НЕ дублирует
 * парсер .env.local):
 *   - app id: параметр app_id (строка/число);
 *   - private key: параметр private_key (inline PEM, для env) ИЛИ
 *     private_key_path (PEM-файл с обязательной проверкой chmod 0600).
 *
 * Fail-fast: при отсутствии обязательных данных, недоступности PEM-файла или
 * нарушении требований к правам доступа выбрасывается
 * {@see InvalidConfigurationException}.
 */
final class LoadGitIdentityConfigService implements LoadGitIdentityConfigServiceInterface
{
    /**
     * @param string|null $appId App id из параметра (строка или null).
     * @param string|null $privateKeyPath Путь к PEM-файлу (предпочтительный источник).
     * @param string|null $privateKey Inline PEM-содержимое (альтернатива для env).
     * @param string $apiBaseUri Базовый URI GitHub API.
     * @param string $githubApiVersion Заголовок X-GitHub-Api-Version.
     * @param string $userAgent User-Agent.
     * @param int $jwtTtlSeconds TTL JWT (1..600).
     * @param int $jwtClockSkewSeconds Бэкдейтинг iat.
     * @param int $tokenExpirySafetyMarginSeconds Safety margin для TTL кеша токена.
     * @param int|null $installationIdCacheTtlSeconds TTL кеша installation id (null = без expiry).
     * @param bool $scopeToRepository Ограничивать ли токен репозиторием.
     * @param int $requestTimeoutSeconds Таймаут HTTP-запросов.
     */
    public function __construct(
        private ?string $appId,
        private ?string $privateKeyPath,
        private ?string $privateKey,
        private string $apiBaseUri,
        private string $githubApiVersion,
        private string $userAgent,
        private int $jwtTtlSeconds,
        private int $jwtClockSkewSeconds,
        private int $tokenExpirySafetyMarginSeconds,
        private ?int $installationIdCacheTtlSeconds,
        private bool $scopeToRepository,
        private int $requestTimeoutSeconds,
    ) {
    }

    #[Override]
    public function load(): GitIdentityConfigVo
    {
        return new GitIdentityConfigVo(
            appId: $this->resolveAppId(),
            privateKey: $this->resolvePrivateKey(),
            apiBaseUri: $this->apiBaseUri,
            githubApiVersion: $this->githubApiVersion,
            userAgent: $this->userAgent,
            jwtTtlSeconds: $this->jwtTtlSeconds,
            jwtClockSkewSeconds: $this->jwtClockSkewSeconds,
            tokenExpirySafetyMarginSeconds: $this->tokenExpirySafetyMarginSeconds,
            installationIdCacheTtlSeconds: $this->installationIdCacheTtlSeconds,
            scopeToRepository: $this->scopeToRepository,
            requestTimeoutSeconds: $this->requestTimeoutSeconds,
        );
    }

    private function resolveAppId(): AppIdVo
    {
        $raw = $this->appId === null ? null : trim($this->appId);
        if ($raw === null || $raw === '') {
            throw new InvalidConfigurationException(
                'GitHub App ID is not configured (parameter task_orchestrator.git_identity.app_id).',
            );
        }
        if (!ctype_digit($raw)) {
            throw new InvalidConfigurationException(
                'GitHub App ID must be a positive integer.',
            );
        }

        return new AppIdVo((int) $raw);
    }

    private function resolvePrivateKey(): PrivateKeyVo
    {
        if ($this->privateKey !== null && trim($this->privateKey) !== '') {
            return new PrivateKeyVo($this->privateKey);
        }

        if ($this->privateKeyPath === null || trim($this->privateKeyPath) === '') {
            throw new InvalidConfigurationException(
                'GitHub App private key is not configured: set private_key or private_key_path.',
            );
        }

        $path = $this->privateKeyPath;
        if (!is_file($path)) {
            throw new InvalidConfigurationException(
                sprintf('GitHub App private key file not found: %s', $path),
            );
        }
        $perms = @fileperms($path);
        if ($perms === false) {
            throw new InvalidConfigurationException(
                sprintf('Cannot determine permissions of private key file: %s', $path),
            );
        }
        if (($perms & 0o077) !== 0) {
            throw new InvalidConfigurationException(
                sprintf('Private key file has insecure permissions (expected 0600): %s', $path),
            );
        }

        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            throw new InvalidConfigurationException(
                sprintf('Failed to read private key file: %s', $path),
            );
        }

        return new PrivateKeyVo($content);
    }
}
