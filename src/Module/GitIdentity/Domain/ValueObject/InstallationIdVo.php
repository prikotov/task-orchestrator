<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject;

use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;

/**
 * Value Object идентификатора установки GitHub App (installation id).
 *
 * Положительное целое число. Источник значения — ответ GitHub API; при
 * некорректном значении выбрасывается {@see InvalidConfigurationException}
 * (см. контракт B/C: «GitHubApiException or InvalidConfigurationException»).
 */
final readonly class InstallationIdVo
{
    public function __construct(private int $value)
    {
        if ($value <= 0) {
            throw new InvalidConfigurationException(
                sprintf('Installation ID must be a positive integer, got %d.', $value),
            );
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function cacheKey(): string
    {
        return (string) $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
