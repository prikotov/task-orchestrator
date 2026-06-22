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
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub\Exception\GitHubHttpException;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub\GitHubHttpComponentInterface;

/**
 * Резолвер installation id через GitHub API.
 *
 * Внешний вызов: `GET {apiBaseUri}/repos/{owner}/{repo}/installation`.
 * Транспортную логику (HTTP-запрос, разбор статуса, обёртку исключений) делегирует
 * {@see GitHubHttpComponentInterface} по конвенции External Service; здесь остаются
 * только формирование URL и разбор ответа.
 */
final class GitHubResolveInstallationIdService implements ResolveInstallationIdServiceInterface
{
    public function __construct(
        private readonly GitHubHttpComponentInterface $http,
    ) {}

    #[Override]
    public function resolve(
        RepoSlugVo $repoSlug,
        JwtTokenVo $jwtToken,
        GitIdentityConfigVo $config,
    ): InstallationIdVo {
        $url = rtrim($config->getApiBaseUri(), '/')
            . '/repos/' . rawurlencode($repoSlug->getOwner()) . '/' . rawurlencode($repoSlug->getRepo())
            . '/installation';

        try {
            $data = $this->http->request('GET', $url, $jwtToken->getValue(), null);
        } catch (GitHubHttpException $e) {
            throw $this->toDomainException($e);
        }

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

    /**
     * Переводит Infrastructure-исключение транспортного слоя в доменное,
     * сохраняя HTTP-статус (для детерминированной классификации 404) и trace
     * через {@see $previous} — граница исключений по конвенции exception.md.
     */
    private function toDomainException(GitHubHttpException $e): GitHubApiException
    {
        $status = $e->getHttpStatus();

        return $status !== null
            ? GitHubApiException::forHttpStatus($status, $e->getMessage(), $e)
            : new GitHubApiException($e->getMessage(), 0, $e);
    }
}
