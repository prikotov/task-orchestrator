<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject;

use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;

/**
 * Value Object нормализованной конфигурации GitIdentity.
 *
 * Агрегирует все настройки модуля в иммутабельном виде: валидирует диапазоны
 * и URI в конструкторе и является единственным носителем конфигурации,
 * передаваемым в доменные интерфейсы сервисов (sign/resolve/request).
 */
final readonly class GitIdentityConfigVo
{
    public function __construct(
        private AppIdVo $appId,
        private PrivateKeyVo $privateKey,
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
        if ($apiBaseUri === '' || filter_var($apiBaseUri, FILTER_VALIDATE_URL) === false) {
            throw new InvalidConfigurationException(
                sprintf('Invalid GitHub API base URI "%s".', $apiBaseUri),
            );
        }
        if ($githubApiVersion === '') {
            throw new InvalidConfigurationException('GitHub API version must not be empty.');
        }
        if ($userAgent === '') {
            throw new InvalidConfigurationException('User-Agent must not be empty.');
        }
        if ($jwtTtlSeconds < 1 || $jwtTtlSeconds > 600) {
            throw new InvalidConfigurationException(
                sprintf('jwt_ttl_seconds must be in range 1..600, got %d.', $jwtTtlSeconds),
            );
        }
        if ($jwtClockSkewSeconds < 0) {
            throw new InvalidConfigurationException(
                sprintf('jwt_clock_skew_seconds must be >= 0, got %d.', $jwtClockSkewSeconds),
            );
        }
        if ($tokenExpirySafetyMarginSeconds < 0) {
            throw new InvalidConfigurationException(
                sprintf('token_expiry_safety_margin_seconds must be >= 0, got %d.', $tokenExpirySafetyMarginSeconds),
            );
        }
        if ($installationIdCacheTtlSeconds !== null && $installationIdCacheTtlSeconds < 0) {
            throw new InvalidConfigurationException(
                sprintf('installation_id_cache_ttl_seconds must be null or >= 0, got %d.', $installationIdCacheTtlSeconds),
            );
        }
        if ($requestTimeoutSeconds < 1) {
            throw new InvalidConfigurationException(
                sprintf('request_timeout_seconds must be >= 1, got %d.', $requestTimeoutSeconds),
            );
        }
    }

    public function getAppId(): AppIdVo
    {
        return $this->appId;
    }

    public function getPrivateKey(): PrivateKeyVo
    {
        return $this->privateKey;
    }

    public function getApiBaseUri(): string
    {
        return $this->apiBaseUri;
    }

    public function getGitHubApiVersion(): string
    {
        return $this->githubApiVersion;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function getJwtTtlSeconds(): int
    {
        return $this->jwtTtlSeconds;
    }

    public function getJwtClockSkewSeconds(): int
    {
        return $this->jwtClockSkewSeconds;
    }

    public function getTokenExpirySafetyMarginSeconds(): int
    {
        return $this->tokenExpirySafetyMarginSeconds;
    }

    public function getInstallationIdCacheTtlSeconds(): ?int
    {
        return $this->installationIdCacheTtlSeconds;
    }

    public function shouldScopeToRepository(): bool
    {
        return $this->scopeToRepository;
    }

    public function getRequestTimeoutSeconds(): int
    {
        return $this->requestTimeoutSeconds;
    }
}
