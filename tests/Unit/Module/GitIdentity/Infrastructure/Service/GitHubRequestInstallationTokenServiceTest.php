<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Infrastructure\Service;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\AppIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\PrivateKeyVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub\GitHubHttpComponentInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\GitHubRequestInstallationTokenService;

/**
 * Unit-тесты {@see GitHubRequestInstallationTokenService}.
 *
 * Транспорт делегирован {@see GitHubHttpComponentInterface} и мокается через
 * createMock — без сети. Проверяем формирование URL эндпоинта выпуска токена
 * (`POST /app/installations/{installation_id}/access_tokens`, подчёркивание,
 * без слэша), тело scoped-запроса и разбор ответа.
 *
 * Сверка с GitHub REST API: Create an installation access token for an app.
 */
#[CoversClass(GitHubRequestInstallationTokenService::class)]
final class GitHubRequestInstallationTokenServiceTest extends TestCase
{
    private const string FIXTURE_PEM = __DIR__ . '/../../fixtures/test-private-key.pem';

    private const string URL = 'https://api.github.test/app/installations/424242/access_tokens';

    private GitIdentityConfigVo $config;

    private JwtTokenVo $jwt;

    private InstallationIdVo $installationId;

    private RepoSlugVo $repoSlug;

    private GitHubHttpComponentInterface&MockObject $http;

    #[\Override]
    protected function setUp(): void
    {
        $this->installationId = new InstallationIdVo(424242);
        $this->repoSlug = RepoSlugVo::fromString('octocat/Hello-World');
        $this->jwt = new JwtTokenVo('header.payload.signature', new DateTimeImmutable('+1 minute'));
        $this->config = $this->buildConfig(true);
        $this->http = $this->createMock(GitHubHttpComponentInterface::class);
    }

    #[Test]
    public function requestTargetsAccessTokensEndpointWithoutSlash(): void
    {
        $this->http
            ->expects(self::once())
            ->method('request')
            ->with('POST', self::URL, $this->jwt->getValue(), self::isType('string'))
            ->willReturn($this->validResponseData());

        $service = new GitHubRequestInstallationTokenService($this->http);
        $token = $service->request($this->installationId, $this->jwt, $this->config, $this->repoSlug);

        // Подчёркивание (access_tokens), устаревший вариант со слэшем недопустим.
        self::assertStringEndsWith('/access_tokens', self::URL);
        self::assertStringNotContainsString('/access/tokens', self::URL);
        self::assertSame('ghs_secret_token', $token->getToken());
    }

    #[Test]
    public function requestWithScopeDisabledSendsNullBody(): void
    {
        $this->config = $this->buildConfig(false);
        $this->http
            ->expects(self::once())
            ->method('request')
            ->with('POST', self::URL, $this->jwt->getValue(), null)
            ->willReturn($this->validResponseData());

        $service = new GitHubRequestInstallationTokenService($this->http);
        $service->request($this->installationId, $this->jwt, $this->config, $this->repoSlug);
    }

    #[Test]
    public function requestWithScopeEnabledSendsRepositoryNamesBody(): void
    {
        $this->http
            ->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                self::URL,
                $this->jwt->getValue(),
                self::callback(static function (?string $body): bool {
                    return $body === '{"repository_names":["octocat/Hello-World"]}';
                }),
            )
            ->willReturn($this->validResponseData());

        $service = new GitHubRequestInstallationTokenService($this->http);
        $service->request($this->installationId, $this->jwt, $this->config, $this->repoSlug);
    }

    #[Test]
    public function requestWithoutTokenInResponseThrows(): void
    {
        $this->http->method('request')->willReturn(['expires_at' => $this->futureIso()]);

        $this->expectException(GitHubApiException::class);

        (new GitHubRequestInstallationTokenService($this->http))->request(
            $this->installationId,
            $this->jwt,
            $this->config,
            $this->repoSlug,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validResponseData(): array
    {
        return ['token' => 'ghs_secret_token', 'expires_at' => $this->futureIso()];
    }

    private function futureIso(): string
    {
        return (new DateTimeImmutable('+1 hour'))->format(DateTimeImmutable::ATOM);
    }

    private function buildConfig(bool $scopeToRepository): GitIdentityConfigVo
    {
        return new GitIdentityConfigVo(
            appId: new AppIdVo(123456),
            privateKey: new PrivateKeyVo((string) file_get_contents(self::FIXTURE_PEM)),
            apiBaseUri: 'https://api.github.test',
            githubApiVersion: '2022-11-28',
            userAgent: 'task-orchestrator-git-identity-test',
            jwtTtlSeconds: 540,
            jwtClockSkewSeconds: 60,
            tokenExpirySafetyMarginSeconds: 60,
            installationIdCacheTtlSeconds: 86400,
            scopeToRepository: $scopeToRepository,
            requestTimeoutSeconds: 30,
        );
    }
}
