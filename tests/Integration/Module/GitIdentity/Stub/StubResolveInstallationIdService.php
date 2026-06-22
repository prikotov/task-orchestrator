<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\GitIdentity\Stub;

use Override;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\ResolveInstallationIdServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;

/**
 * Test double для {@see ResolveInstallationIdServiceInterface}.
 *
 * Возвращает заранее сконфигурированный installation id и подсчитывает
 * количество вызовов, чтобы интеграционный тест мог убедиться в отсутствии
 * сетевых обращений при cache-hit. Опционально выбрасывает GitHubApiException
 * для проверки обработки ошибок.
 */
final class StubResolveInstallationIdService implements ResolveInstallationIdServiceInterface
{
    public int $callCount = 0;

    public function __construct(
        private readonly InstallationIdVo $installationId,
        private readonly bool $shouldFail = false,
    ) {
    }

    #[Override]
    public function resolve(
        RepoSlugVo $repoSlug,
        JwtTokenVo $jwtToken,
        GitIdentityConfigVo $config,
    ): InstallationIdVo {
        ++$this->callCount;

        if ($this->shouldFail) {
            throw new GitHubApiException('GitHub API error: HTTP 404 for GET /installation');
        }

        return $this->installationId;
    }
}
