<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\Service;

use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;

/**
 * Запрашивает installation access token у GitHub.
 *
 * Внешний вызов: `POST {apiBaseUri}/app/installations/{installation_id}/access_tokens`.
 * При scope_to_repository=true запрос ограничивается репозиторием (repository_names).
 */
interface RequestInstallationTokenServiceInterface
{
    /**
     * @throws GitHubApiException при сетевой ошибке, не-2xx ответе или неожиданной структуре.
     */
    public function request(
        InstallationIdVo $installationId,
        JwtTokenVo $jwtToken,
        GitIdentityConfigVo $config,
        RepoSlugVo $repoSlug,
    ): InstallationTokenVo;
}
