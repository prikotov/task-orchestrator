<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\GitIdentity\Stub;

use Override;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\LoadGitIdentityConfigServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\AppIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\PrivateKeyVo;

/**
 * Test double для {@see LoadGitIdentityConfigServiceInterface}: возвращает
 * детерминированную валидную конфигурацию с фикстурным PEM. Не обращается к сети
 * и не читает env.
 */
final class StubGitIdentityConfigLoader implements LoadGitIdentityConfigServiceInterface
{
    public function __construct(
        private readonly string $privateKeyPem,
        private readonly int $appId = 123456,
        private readonly int $safetyMarginSeconds = 60,
    ) {
    }

    #[Override]
    public function load(): GitIdentityConfigVo
    {
        return new GitIdentityConfigVo(
            appId: new AppIdVo($this->appId),
            privateKey: new PrivateKeyVo($this->privateKeyPem),
            apiBaseUri: 'https://api.github.com',
            githubApiVersion: '2026-03-10',
            userAgent: 'task-orchestrator-git-identity-test',
            jwtTtlSeconds: 540,
            jwtClockSkewSeconds: 60,
            tokenExpirySafetyMarginSeconds: $this->safetyMarginSeconds,
            installationIdCacheTtlSeconds: 86400,
            scopeToRepository: true,
            requestTimeoutSeconds: 30,
        );
    }
}
