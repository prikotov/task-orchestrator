<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service;

use JsonException;
use Override;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\RequestInstallationTokenServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;

/**
 * Запрос installation access token у GitHub.
 *
 * Внешний вызов: `POST {apiBaseUri}/app/installations/{installation_id}/access_tokens`.
 * При {@see GitIdentityConfigVo::shouldScopeToRepository()}=true запрос ограничивается
 * репозиторием через `repository_names` (контракт, допущение 5).
 */
final class GitHubRequestInstallationTokenService implements RequestInstallationTokenServiceInterface
{
    use GitHubHttpTransportTrait;

    #[Override]
    public function request(
        InstallationIdVo $installationId,
        JwtTokenVo $jwtToken,
        GitIdentityConfigVo $config,
        RepoSlugVo $repoSlug,
    ): InstallationTokenVo {
        $url = rtrim($config->getApiBaseUri(), '/')
            . '/app/installations/' . $installationId->getValue() . '/access_tokens';

        $body = $config->shouldScopeToRepository() ? $this->buildScopedBody($repoSlug) : null;
        $response = $this->githubRequest('POST', $url, $jwtToken->getValue(), $config, $body);
        $data = $this->githubDecodeJson($response);

        $token = $data['token'] ?? null;
        $expiresAtRaw = $data['expires_at'] ?? null;
        if (!is_string($token) || !is_string($expiresAtRaw)) {
            throw new GitHubApiException('GitHub API: token or expires_at not found in response.');
        }

        $expiresAt = $this->parseExpiresAt($expiresAtRaw);

        return new InstallationTokenVo($token, $expiresAt, $installationId);
    }

    private function buildScopedBody(RepoSlugVo $repoSlug): string
    {
        try {
            return json_encode(
                ['repository_names' => [$repoSlug->toString()]],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $e) {
            throw new GitHubApiException('Failed to encode token request body.', 0, $e);
        }
    }

    private function parseExpiresAt(string $value): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable $e) {
            throw new GitHubApiException('GitHub API: invalid expires_at value in response.', 0, $e);
        }
    }
}
