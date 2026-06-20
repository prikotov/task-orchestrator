<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\Service;

use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;

/**
 * Резолвит installation id GitHub App для репозитория.
 *
 * Внешний вызов: `GET {apiBaseUri}/repos/{owner}/{repo}/installation`.
 */
interface ResolveInstallationIdServiceInterface
{
    /**
     * @throws GitHubApiException при сетевой ошибке, не-2xx ответе или неожиданной структуре.
     */
    public function resolve(
        RepoSlugVo $repoSlug,
        JwtTokenVo $jwtToken,
        GitIdentityConfigVo $config,
    ): InstallationIdVo;
}
