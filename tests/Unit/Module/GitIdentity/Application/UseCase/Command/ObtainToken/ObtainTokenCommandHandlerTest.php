<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Application\UseCase\Command\ObtainToken;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Application\Exception\ObtainTokenFailedException;
use TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken\ObtainTokenCommand;
use TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken\ObtainTokenCommandHandler;
use TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken\ObtainTokenResultDto;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\ClockServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\LoadGitIdentityConfigServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\RequestInstallationTokenServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\ResolveInstallationIdServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\SignJwtTokenServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\TokenCacheInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\AppIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\PrivateKeyVo;

#[CoversClass(ObtainTokenCommandHandler::class)]
final class ObtainTokenCommandHandlerTest extends TestCase
{
    private const FIXTURE_PEM = __DIR__ . '/../../../../fixtures/test-private-key.pem';

    private const REPO = 'octocat/Hello-World';

    private DateTimeImmutable $now;

    private GitIdentityConfigVo $config;

    private JwtTokenVo $jwt;

    private InstallationIdVo $installationId;

    private InstallationTokenVo $freshToken;

    private LoadGitIdentityConfigServiceInterface&MockObject $configLoader;

    private TokenCacheInterface&MockObject $cache;

    private SignJwtTokenServiceInterface&MockObject $jwtSigner;

    private ResolveInstallationIdServiceInterface&MockObject $resolver;

    private RequestInstallationTokenServiceInterface&MockObject $requester;

    private ClockServiceInterface&MockObject $clock;

    private ObtainTokenCommandHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable();

        $this->config = new GitIdentityConfigVo(
            appId: new AppIdVo(123456),
            privateKey: new PrivateKeyVo((string) file_get_contents(self::FIXTURE_PEM)),
            apiBaseUri: 'https://api.github.com',
            githubApiVersion: '2026-03-10',
            userAgent: 'task-orchestrator-git-identity-test',
            jwtTtlSeconds: 540,
            jwtClockSkewSeconds: 60,
            tokenExpirySafetyMarginSeconds: 60,
            installationIdCacheTtlSeconds: 86400,
            scopeToRepository: true,
            requestTimeoutSeconds: 30,
        );

        $this->jwt = new JwtTokenVo('header.payload.signature', $this->now->modify('+1 minute'));
        $this->installationId = new InstallationIdVo(424242);
        $this->freshToken = new InstallationTokenVo(
            'ghs_fresh_token',
            $this->now->modify('+1 hour'),
            $this->installationId,
        );

        $this->configLoader = $this->createMock(LoadGitIdentityConfigServiceInterface::class);
        $this->cache = $this->createMock(TokenCacheInterface::class);
        $this->jwtSigner = $this->createMock(SignJwtTokenServiceInterface::class);
        $this->resolver = $this->createMock(ResolveInstallationIdServiceInterface::class);
        $this->requester = $this->createMock(RequestInstallationTokenServiceInterface::class);
        $this->clock = $this->createMock(ClockServiceInterface::class);

        $this->clock->method('now')->willReturn($this->now);
        $this->configLoader->method('load')->willReturn($this->config);

        $this->handler = new ObtainTokenCommandHandler(
            $this->configLoader,
            $this->cache,
            $this->jwtSigner,
            $this->resolver,
            $this->requester,
            $this->clock,
        );
    }

    #[Test]
    public function cacheHitReturnsTokenWithoutNetworkCalls(): void
    {
        $validToken = new InstallationTokenVo(
            'ghs_cached_token',
            $this->now->modify('+30 minutes'),
            $this->installationId,
        );

        $this->cache
            ->expects(self::once())
            ->method('readInstallationId')
            ->willReturn($this->installationId);
        $this->cache
            ->expects(self::once())
            ->method('readInstallationToken')
            ->with(self::identicalTo($this->installationId))
            ->willReturn($validToken);

        $this->jwtSigner->expects(self::never())->method('sign');
        $this->resolver->expects(self::never())->method('resolve');
        $this->requester->expects(self::never())->method('request');
        $this->cache->expects(self::never())->method('writeInstallationToken');
        $this->cache->expects(self::never())->method('writeInstallationId');

        $result = ($this->handler)(new ObtainTokenCommand(self::REPO));

        self::assertSame('ghs_cached_token', $result->token);
        self::assertSame($validToken->getExpiresAt(), $result->expiresAt);
        self::assertSame(424242, $result->installationId);
        self::assertInstanceOf(ObtainTokenResultDto::class, $result);
    }

    #[Test]
    public function cacheMissResolvesInstallationIdRequestsTokenAndWritesBothCaches(): void
    {
        // installation_id cache miss
        $this->cache
            ->expects(self::once())
            ->method('readInstallationId')
            ->willReturn(null);
        // token cache miss
        $this->cache
            ->expects(self::once())
            ->method('readInstallationToken')
            ->willReturn(null);

        $this->jwtSigner
            ->expects(self::once())
            ->method('sign')
            ->with(self::identicalTo($this->config), self::identicalTo($this->now))
            ->willReturn($this->jwt);

        $this->resolver
            ->expects(self::once())
            ->method('resolve')
            ->willReturn($this->installationId);

        $this->requester
            ->expects(self::once())
            ->method('request')
            ->willReturn($this->freshToken);

        $expectedTtl = $this->freshToken->cacheTtlSeconds($this->now, $this->config->getTokenExpirySafetyMarginSeconds());

        $this->cache
            ->expects(self::once())
            ->method('writeInstallationId')
            ->with(
                self::callback(fn ($slug) => $slug->toString() === self::REPO),
                self::identicalTo($this->installationId),
                $this->config->getInstallationIdCacheTtlSeconds(),
            );
        $this->cache
            ->expects(self::once())
            ->method('writeInstallationToken')
            ->with(self::isInstanceOf(InstallationTokenVo::class), self::identicalTo($expectedTtl));

        $result = ($this->handler)(new ObtainTokenCommand(self::REPO));

        self::assertSame('ghs_fresh_token', $result->token);
        self::assertSame($this->freshToken->getExpiresAt(), $result->expiresAt);
        self::assertSame(424242, $result->installationId);
    }

    #[Test]
    public function cachedInstallationIdWithExpiredTokenReusesInstallationIdWithoutResolve(): void
    {
        // installation_id cached
        $this->cache
            ->expects(self::once())
            ->method('readInstallationId')
            ->willReturn($this->installationId);
        // token cache miss (expired)
        $this->cache
            ->expects(self::once())
            ->method('readInstallationToken')
            ->with(self::identicalTo($this->installationId))
            ->willReturn(null);

        // JWT is signed (token refresh path), but resolve MUST be skipped.
        $this->jwtSigner
            ->expects(self::once())
            ->method('sign')
            ->willReturn($this->jwt);
        $this->resolver->expects(self::never())->method('resolve');
        $this->requester
            ->expects(self::once())
            ->method('request')
            ->willReturn($this->freshToken);

        $this->cache->expects(self::never())->method('writeInstallationId');
        $this->cache->expects(self::once())->method('writeInstallationToken');

        $result = ($this->handler)(new ObtainTokenCommand(self::REPO));

        self::assertSame('ghs_fresh_token', $result->token);
    }

    #[Test]
    public function cachedInstallationIdWithTokenWithinSafetyMarginRefreshesToken(): void
    {
        // Token exists but is unusable: expiry within the 60s safety margin.
        $unusableToken = new InstallationTokenVo(
            'ghs_almost_expired',
            $this->now->modify('+30 seconds'),
            $this->installationId,
        );

        $this->cache->method('readInstallationId')->willReturn($this->installationId);
        $this->cache
            ->expects(self::once())
            ->method('readInstallationToken')
            ->willReturn($unusableToken);

        $this->jwtSigner->expects(self::once())->method('sign')->willReturn($this->jwt);
        $this->resolver->expects(self::never())->method('resolve');
        $this->requester->expects(self::once())->method('request')->willReturn($this->freshToken);

        $result = ($this->handler)(new ObtainTokenCommand(self::REPO));

        self::assertSame('ghs_fresh_token', $result->token);
    }

    #[Test]
    public function requestFailurePropagatesAsObtainTokenFailedException(): void
    {
        $this->cache->method('readInstallationId')->willReturn(null);
        $this->cache->method('readInstallationToken')->willReturn(null);
        $this->jwtSigner->method('sign')->willReturn($this->jwt);
        $this->resolver->method('resolve')->willReturn($this->installationId);
        $this->requester
            ->expects(self::once())
            ->method('request')
            ->willThrowException(new GitHubApiException('GitHub API error: HTTP 500 for POST /access_tokens'));

        $this->expectException(ObtainTokenFailedException::class);
        $this->expectExceptionMessageMatches('/HTTP 500/');

        ($this->handler)(new ObtainTokenCommand(self::REPO));
    }

    #[Test]
    public function resolveFailurePropagates(): void
    {
        $this->cache->method('readInstallationId')->willReturn(null);
        $this->jwtSigner->method('sign')->willReturn($this->jwt);
        $this->resolver
            ->expects(self::once())
            ->method('resolve')
            ->willThrowException(new GitHubApiException('GitHub API error: HTTP 404 for GET /installation'));

        $this->expectException(ObtainTokenFailedException::class);
        $this->expectExceptionMessageMatches('/HTTP 404/');

        ($this->handler)(new ObtainTokenCommand(self::REPO));
    }

    #[Test]
    public function configLoaderFailurePropagates(): void
    {
        $this->configLoader = $this->createMock(LoadGitIdentityConfigServiceInterface::class);
        $this->configLoader
            ->method('load')
            ->willThrowException(new InvalidConfigurationException('GitHub App ID is not configured.'));
        $this->handler = new ObtainTokenCommandHandler(
            $this->configLoader,
            $this->cache,
            $this->jwtSigner,
            $this->resolver,
            $this->requester,
            $this->clock,
        );

        $this->expectException(ObtainTokenFailedException::class);
        $this->expectExceptionMessage('GitHub App ID is not configured.');

        ($this->handler)(new ObtainTokenCommand(self::REPO));
    }

    #[Test]
    public function invalidRepoSlugThrowsConfigurationException(): void
    {
        $this->expectException(ObtainTokenFailedException::class);

        ($this->handler)(new ObtainTokenCommand('not-a-valid-slug'));
    }

    #[Test]
    public function cachedInstallationIdWith404OnRequestInvalidatesAndRetriesOnce(): void
    {
        // Сценарий M1: cache-hit installation_id + cache-miss токена + 404 на request().
        // installation удалена/переустановлена → устаревший installation_id инвалидируется,
        // resolve повторяется 1 раз, request повторяется 1 раз и возвращает свежий токен.
        $reinstalledId = new InstallationIdVo(555555);

        $this->cache->method('readInstallationId')->willReturn($this->installationId);
        $this->cache->method('readInstallationToken')->willReturn(null);
        $this->jwtSigner->expects(self::once())->method('sign')->willReturn($this->jwt);

        $this->cache
            ->expects(self::once())
            ->method('invalidateInstallationId')
            ->with(self::callback(fn ($slug) => $slug->toString() === self::REPO));

        // resolve вызывается ровно один раз — только на повторе после инвалидации.
        $this->resolver
            ->expects(self::once())
            ->method('resolve')
            ->willReturn($reinstalledId);

        $this->cache
            ->expects(self::once())
            ->method('writeInstallationId')
            ->with(
                self::callback(fn ($slug) => $slug->toString() === self::REPO),
                self::identicalTo($reinstalledId),
                $this->config->getInstallationIdCacheTtlSeconds(),
            );

        $calls = 0;
        $this->requester
            ->expects(self::exactly(2))
            ->method('request')
            ->willReturnCallback(function () use (&$calls) {
                ++$calls;
                if ($calls === 1) {
                    throw GitHubApiException::forHttpStatus(
                        404,
                        'GitHub API error: HTTP 404 for POST /access_tokens',
                    );
                }

                return $this->freshToken;
            });

        $this->cache->expects(self::once())->method('writeInstallationToken');

        $result = ($this->handler)(new ObtainTokenCommand(self::REPO));

        self::assertSame('ghs_fresh_token', $result->token);
    }

    #[Test]
    public function cacheMissInstallationIdWith404OnRequestDoesNotRetry(): void
    {
        // Свежий resolve на шаге 3 (cache-miss installation_id): при 404 на request()
        // повтор resolve/request лишён смысла — исключение пробрасывается как есть.
        $this->cache->method('readInstallationId')->willReturn(null);
        $this->cache->method('readInstallationToken')->willReturn(null);
        $this->jwtSigner->method('sign')->willReturn($this->jwt);
        $this->resolver->expects(self::once())->method('resolve')->willReturn($this->installationId);
        $this->cache->expects(self::never())->method('invalidateInstallationId');
        $this->requester
            ->expects(self::once())
            ->method('request')
            ->willThrowException(GitHubApiException::forHttpStatus(404, 'HTTP 404'));

        $this->expectException(ObtainTokenFailedException::class);
        $this->expectExceptionMessageMatches('/HTTP 404/');

        ($this->handler)(new ObtainTokenCommand(self::REPO));
    }

    #[Test]
    public function cachedInstallationIdWithNon404ErrorDoesNotRetry(): void
    {
        // cache-hit installation_id, но ошибка не 404 (например, 500) → проброс без retry.
        $this->cache->method('readInstallationId')->willReturn($this->installationId);
        $this->cache->method('readInstallationToken')->willReturn(null);
        $this->jwtSigner->method('sign')->willReturn($this->jwt);
        $this->resolver->expects(self::never())->method('resolve');
        $this->cache->expects(self::never())->method('invalidateInstallationId');
        $this->requester
            ->expects(self::once())
            ->method('request')
            ->willThrowException(GitHubApiException::forHttpStatus(500, 'HTTP 500'));

        $this->expectException(ObtainTokenFailedException::class);
        $this->expectExceptionMessageMatches('/HTTP 500/');

        ($this->handler)(new ObtainTokenCommand(self::REPO));
    }
}
