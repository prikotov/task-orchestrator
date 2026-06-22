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
 * Загрузчик конфигурации GitIdentity (чтение env с дефолтами-константами).
 *
 * Конфигурация модуля принадлежит самому модулю и читается из env-переменных
 * (их загружает `bin/console` через Symfony Dotenv при старте). Дефолты — это
 * знание модуля о протоколе GitHub App auth, поэтому они живут здесь как
 * private-константы, а не в Configuration бандла.
 *
 * Источники env:
 *   - AGENT_APP_ID (обязательный, число > 0);
 *   - AGENT_PRIVATE_KEY (inline PEM, приоритет) ИЛИ AGENT_PRIVATE_KEY_PATH
 *     (PEM-файл с обязательной проверкой chmod 0600);
 *   - AGENT_API_BASE_URI, AGENT_GITHUB_API_VERSION, AGENT_USER_AGENT,
 *     AGENT_JWT_TTL_SECONDS, AGENT_JWT_CLOCK_SKEW_SECONDS,
 *     AGENT_TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS,
 *     AGENT_INSTALLATION_ID_CACHE_TTL_SECONDS, AGENT_SCOPE_TO_REPOSITORY,
 *     AGENT_REQUEST_TIMEOUT_SECONDS — опциональные, с дефолтами-константами.
 *
 * Fail-fast: при отсутствии обязательных данных, недоступности PEM-файла,
 * нарушении требований к правам доступа (chmod) или нечисловых значениях
 * выбрасывается {@see InvalidConfigurationException}. Диапазоны числовых
 * параметров и валидность URI дополнительно проверяются в {@see GitIdentityConfigVo}.
 */
final class LoadGitIdentityConfigService implements LoadGitIdentityConfigServiceInterface
{
    /** Базовый URI GitHub API (переопределяется для GitHub Enterprise). */
    private const string DEFAULT_API_BASE_URI = 'https://api.github.com';

    /**
     * Текущий стабильный X-GitHub-Api-Version (дата Versioning API GitHub).
     *
     * @see https://docs.github.com/rest/overview/api-versions
     */
    private const string DEFAULT_GITHUB_API_VERSION = '2022-11-28';

    /** HTTP User-Agent (требование best practice GitHub). */
    private const string DEFAULT_USER_AGENT = 'task-orchestrator-git-identity';

    /** TTL JWT в секундах (GitHub допускает максимум 600). */
    private const int DEFAULT_JWT_TTL_SECONDS = 540;

    /** Сдвиг iat назад (толерантность к drift NTP). */
    private const int DEFAULT_JWT_CLOCK_SKEW_SECONDS = 60;

    /** Запас, вычитаемый из expiry для TTL кеша токена. */
    private const int DEFAULT_TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS = 60;

    /** TTL кеша installation id (null = без expiry). */
    private const int DEFAULT_INSTALLATION_ID_CACHE_TTL_SECONDS = 86400;

    /** Ограничивать ли installation token запрошенным репозиторием. */
    private const bool DEFAULT_SCOPE_TO_REPOSITORY = true;

    /** Таймаут HTTP-запросов к GitHub. */
    private const int DEFAULT_REQUEST_TIMEOUT_SECONDS = 30;

    private const string ENV_APP_ID = 'AGENT_APP_ID';

    private const string ENV_PRIVATE_KEY = 'AGENT_PRIVATE_KEY';

    private const string ENV_PRIVATE_KEY_PATH = 'AGENT_PRIVATE_KEY_PATH';

    private const string ENV_API_BASE_URI = 'AGENT_API_BASE_URI';

    private const string ENV_GITHUB_API_VERSION = 'AGENT_GITHUB_API_VERSION';

    private const string ENV_USER_AGENT = 'AGENT_USER_AGENT';

    private const string ENV_JWT_TTL_SECONDS = 'AGENT_JWT_TTL_SECONDS';

    private const string ENV_JWT_CLOCK_SKEW_SECONDS = 'AGENT_JWT_CLOCK_SKEW_SECONDS';

    private const string ENV_TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS = 'AGENT_TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS';

    private const string ENV_INSTALLATION_ID_CACHE_TTL_SECONDS = 'AGENT_INSTALLATION_ID_CACHE_TTL_SECONDS';

    private const string ENV_SCOPE_TO_REPOSITORY = 'AGENT_SCOPE_TO_REPOSITORY';

    private const string ENV_REQUEST_TIMEOUT_SECONDS = 'AGENT_REQUEST_TIMEOUT_SECONDS';

    #[Override]
    public function load(): GitIdentityConfigVo
    {
        return new GitIdentityConfigVo(
            appId: $this->resolveAppId(),
            privateKey: $this->resolvePrivateKey(),
            apiBaseUri: $this->readString(self::ENV_API_BASE_URI, self::DEFAULT_API_BASE_URI),
            githubApiVersion: $this->readString(self::ENV_GITHUB_API_VERSION, self::DEFAULT_GITHUB_API_VERSION),
            userAgent: $this->readString(self::ENV_USER_AGENT, self::DEFAULT_USER_AGENT),
            jwtTtlSeconds: $this->readInt(self::ENV_JWT_TTL_SECONDS, self::DEFAULT_JWT_TTL_SECONDS),
            jwtClockSkewSeconds: $this->readInt(
                self::ENV_JWT_CLOCK_SKEW_SECONDS,
                self::DEFAULT_JWT_CLOCK_SKEW_SECONDS,
            ),
            tokenExpirySafetyMarginSeconds: $this->readInt(
                self::ENV_TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS,
                self::DEFAULT_TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS,
            ),
            installationIdCacheTtlSeconds: $this->readNullableInt(
                self::ENV_INSTALLATION_ID_CACHE_TTL_SECONDS,
                self::DEFAULT_INSTALLATION_ID_CACHE_TTL_SECONDS,
            ),
            scopeToRepository: $this->readBool(
                self::ENV_SCOPE_TO_REPOSITORY,
                self::DEFAULT_SCOPE_TO_REPOSITORY,
            ),
            requestTimeoutSeconds: $this->readInt(
                self::ENV_REQUEST_TIMEOUT_SECONDS,
                self::DEFAULT_REQUEST_TIMEOUT_SECONDS,
            ),
        );
    }

    private function resolveAppId(): AppIdVo
    {
        $raw = $this->envString(self::ENV_APP_ID);
        if ($raw === null) {
            throw new InvalidConfigurationException(
                sprintf('GitHub App ID is not configured (env %s).', self::ENV_APP_ID),
            );
        }
        if (!ctype_digit($raw)) {
            throw new InvalidConfigurationException(
                sprintf('GitHub App ID must be a positive integer, got "%s".', $raw),
            );
        }

        return new AppIdVo((int) $raw);
    }

    private function resolvePrivateKey(): PrivateKeyVo
    {
        $inline = $this->envString(self::ENV_PRIVATE_KEY);
        if ($inline !== null) {
            return new PrivateKeyVo($inline);
        }

        $path = $this->envString(self::ENV_PRIVATE_KEY_PATH);
        if ($path === null) {
            throw new InvalidConfigurationException(
                sprintf(
                    'GitHub App private key is not configured: set %s or %s.',
                    self::ENV_PRIVATE_KEY,
                    self::ENV_PRIVATE_KEY_PATH,
                ),
            );
        }

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

    /**
     * Читает строковую env-переменную; null — если не задана или пустая
     * (состоит только из пробелов). Возвращает значение как есть (без trim),
     * чтобы preserve'ить значащие байты (например, trailing-newline PEM).
     */
    private function envString(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false) {
            return null;
        }

        return trim($value) === '' ? null : $value;
    }

    private function readString(string $name, string $default): string
    {
        $raw = $this->envString($name);

        return $raw === null ? $default : trim($raw);
    }

    private function readInt(string $name, int $default): int
    {
        $raw = $this->envString($name);
        if ($raw === null) {
            return $default;
        }
        $raw = trim($raw);
        // Целое должно быть чистым (без десятичных/научной нотации).
        if (!preg_match('/^[+-]?[0-9]+$/', $raw)) {
            throw new InvalidConfigurationException(
                sprintf('Env %s must be an integer, got "%s".', $name, $raw),
            );
        }

        return (int) $raw;
    }

    /**
     * Читает целочисленную env-переменную с поддержкой явного «null»
     * (строка "null"/"none" независимо от регистра) — означает «без expiry».
     */
    private function readNullableInt(string $name, int $default): ?int
    {
        $raw = $this->envString($name);
        if ($raw === null) {
            return $default;
        }
        $raw = trim($raw);
        if (in_array(strtolower($raw), ['null', 'none'], true)) {
            return null;
        }
        if (!preg_match('/^[+-]?[0-9]+$/', $raw)) {
            throw new InvalidConfigurationException(
                sprintf('Env %s must be an integer or "null", got "%s".', $name, $raw),
            );
        }

        return (int) $raw;
    }

    private function readBool(string $name, bool $default): bool
    {
        $raw = $this->envString($name);
        if ($raw === null) {
            return $default;
        }
        $raw = strtolower(trim($raw));
        return match ($raw) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new InvalidConfigurationException(
                sprintf('Env %s must be a boolean (true|false|1|0|yes|no|on|off), got "%s".', $name, $raw),
            ),
        };
    }
}
