<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\GitIdentity\Stub;

use Override;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\RequestInstallationTokenServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;

/**
 * Test double для {@see RequestInstallationTokenServiceInterface}.
 *
 * Возвращает токен с заданным сроком жизни и подсчитывает вызовы. Опционально
 * выбрасывает GitHubApiException для проверки обработки ошибок.
 */
final class StubRequestInstallationTokenService implements RequestInstallationTokenServiceInterface
{
    public int $callCount = 0;

    public function __construct(
        private readonly InstallationIdVo $installationId,
        private readonly string $tokenValue,
        private readonly \Closure $now,
        private readonly int $lifetimeSeconds = 3600,
        private readonly bool $shouldFail = false,
    ) {
    }

    #[Override]
    public function request(
        InstallationIdVo $installationId,
        JwtTokenVo $jwtToken,
        GitIdentityConfigVo $config,
        RepoSlugVo $repoSlug,
    ): InstallationTokenVo {
        ++$this->callCount;

        if ($this->shouldFail) {
            throw new GitHubApiException('GitHub API error: HTTP 500 for POST /access_tokens');
        }

        return new InstallationTokenVo(
            $this->tokenValue,
            ($this->now)()->modify(sprintf('+%d seconds', $this->lifetimeSeconds)),
            $this->installationId,
        );
    }
}
