<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service;

use Override;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\ResolveInstallationIdServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;

/**
 * Резолвер installation id через GitHub API.
 *
 * Внешний вызов: `GET {apiBaseUri}/repos/{owner}/{repo}/installation`.
 * Без сторонних зависимостей (file_get_contents + stream_context).
 */
final class GitHubResolveInstallationIdService implements ResolveInstallationIdServiceInterface
{
    use GitHubHttpTransportTrait;

    #[Override]
    public function resolve(
        RepoSlugVo $repoSlug,
        JwtTokenVo $jwtToken,
        GitIdentityConfigVo $config,
    ): InstallationIdVo {
        $url = rtrim($config->getApiBaseUri(), '/')
            . '/repos/' . rawurlencode($repoSlug->getOwner()) . '/' . rawurlencode($repoSlug->getRepo())
            . '/installation';

        $body = $this->githubRequest('GET', $url, $jwtToken->getValue(), $config, null);
        $data = $this->githubDecodeJson($body);

        $id = $data['id'] ?? null;
        if (!is_int($id)) {
            throw new GitHubApiException('GitHub API: installation ID not found in response.');
        }

        try {
            return new InstallationIdVo($id);
        } catch (InvalidConfigurationException $e) {
            throw new GitHubApiException('GitHub API: invalid installation ID in response.', 0, $e);
        }
    }
}
