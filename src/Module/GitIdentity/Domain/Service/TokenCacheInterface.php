<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\Service;

use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitIdentityException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;

/**
 * Кеш installation token и installation id.
 *
 * Ключи:
 *   - installation token — по installation id;
 *   - installation id — по паре owner/repo.
 *
 * Контракт: TTL token-записи вычисляет Application и передаёт в write.
 * Повреждённый кеш должен обрабатываться gracefully (cache-miss), а не падать.
 */
interface TokenCacheInterface
{
    /**
     * @return InstallationTokenVo|null закешированный токен или null при cache-miss.
     *
     * @throws GitIdentityException при runtime ошибке I/O кеша.
     */
    public function readInstallationToken(InstallationIdVo $installationId): ?InstallationTokenVo;

    /**
     * @param int $ttlSeconds сколько секунд токен ещё безопасно валиден (с safety margin).
     *
     * @throws GitIdentityException при runtime ошибке I/O кеша.
     */
    public function writeInstallationToken(InstallationTokenVo $token, int $ttlSeconds): void;

    /**
     * @throws GitIdentityException при runtime ошибке I/O кеша.
     */
    public function invalidateInstallationToken(InstallationIdVo $installationId): void;

    /**
     * @return InstallationIdVo|null закешированный installation id или null при cache-miss/expiry.
     *
     * @throws GitIdentityException при runtime ошибке I/O кеша.
     */
    public function readInstallationId(RepoSlugVo $repoSlug): ?InstallationIdVo;

    /**
     * @param int|null $ttlSeconds TTL записи; null — без проверки expiry.
     *
     * @throws GitIdentityException при runtime ошибке I/O кеша.
     */
    public function writeInstallationId(
        RepoSlugVo $repoSlug,
        InstallationIdVo $installationId,
        ?int $ttlSeconds,
    ): void;

    /**
     * @throws GitIdentityException при runtime ошибке I/O кеша.
     */
    public function invalidateInstallationId(RepoSlugVo $repoSlug): void;
}
