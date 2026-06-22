<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\GitIdentity\Stub;

use DateTimeImmutable;
use Override;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\TokenCacheInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;

/**
 * In-memory реализация {@see TokenCacheInterface} для интеграционных тестов.
 *
 * Эмулирует TTL: при чтении проверяет, не истекла ли запись относительно
 * инжектированного источника времени ($now), чтобы воспроизводить семантику
 * файлового кеша без реальной ФС.
 */
final class InMemoryTokenCache implements TokenCacheInterface
{
    /** @var array<string, InstallationTokenVo> */
    private array $tokens = [];

    /** @var array<string, array{id: InstallationIdVo, expiresAt: DateTimeImmutable|null}> */
    private array $installationIds = [];

    public function __construct(
        private readonly \Closure $now,
    ) {
    }

    #[Override]
    public function readInstallationToken(InstallationIdVo $installationId): ?InstallationTokenVo
    {
        $token = $this->tokens[$installationId->cacheKey()] ?? null;
        if ($token === null) {
            return null;
        }

        $now = ($this->now)();
        if ($now >= $token->getExpiresAt()) {
            return null;
        }

        return $token;
    }

    #[Override]
    public function writeInstallationToken(InstallationTokenVo $token, int $ttlSeconds): void
    {
        $this->tokens[$token->getInstallationId()->cacheKey()] = $token;
    }

    #[Override]
    public function invalidateInstallationToken(InstallationIdVo $installationId): void
    {
        unset($this->tokens[$installationId->cacheKey()]);
    }

    #[Override]
    public function readInstallationId(RepoSlugVo $repoSlug): ?InstallationIdVo
    {
        $entry = $this->installationIds[$repoSlug->cacheKey()] ?? null;
        if ($entry === null) {
            return null;
        }

        if ($entry['expiresAt'] !== null) {
            $now = ($this->now)();
            if ($now >= $entry['expiresAt']) {
                return null;
            }
        }

        return $entry['id'];
    }

    #[Override]
    public function writeInstallationId(
        RepoSlugVo $repoSlug,
        InstallationIdVo $installationId,
        ?int $ttlSeconds,
    ): void {
        $expiresAt = $ttlSeconds === null
            ? null
            : ($this->now)()->modify(sprintf('+%d seconds', max(0, $ttlSeconds)));

        $this->installationIds[$repoSlug->cacheKey()] = [
            'id' => $installationId,
            'expiresAt' => $expiresAt,
        ];
    }

    #[Override]
    public function invalidateInstallationId(RepoSlugVo $repoSlug): void
    {
        unset($this->installationIds[$repoSlug->cacheKey()]);
    }
}
