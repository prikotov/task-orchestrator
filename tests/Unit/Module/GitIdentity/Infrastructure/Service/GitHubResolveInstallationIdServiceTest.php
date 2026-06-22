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
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\PrivateKeyVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub\Exception\GitHubHttpException;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub\GitHubHttpComponentInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\GitHubResolveInstallationIdService;

/**
 * Unit-тесты {@see GitHubResolveInstallationIdService}.
 *
 * Транспорт делегирован {@see GitHubHttpComponentInterface} и мокается через
 * createMock — без сети. Тест проверяет формирование URL эндпоинта
 * `GET /repos/{owner}/{repo}/installation`, корректность проброса JWT в bearer и
 * разбор ответа. Сверка с GitHub REST API: List installations for a repository
 * (installation lookup by repo).
 */
#[CoversClass(GitHubResolveInstallationIdService::class)]
final class GitHubResolveInstallationIdServiceTest extends TestCase
{
    private const string FIXTURE_PEM = __DIR__ . '/../../fixtures/test-private-key.pem';

    private GitIdentityConfigVo $config;

    private JwtTokenVo $jwt;

    private GitHubHttpComponentInterface&MockObject $http;

    #[\Override]
    protected function setUp(): void
    {
        $this->config = $this->buildConfig();
        $this->jwt = new JwtTokenVo('header.payload.signature', new DateTimeImmutable('+1 minute'));
        $this->http = $this->createMock(GitHubHttpComponentInterface::class);
    }

    #[Test]
    public function resolveTargetsInstallationEndpoint(): void
    {
        // Захватываем URL, переданный в HTTP-компонент.
        $this->http
            ->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                self::callback(static function (string $url): bool {
                    return $url === 'https://api.github.test/repos/octocat/Hello-World/installation';
                }),
                $this->jwt->getValue(),
                null,
            )
            ->willReturn(['id' => 424242]);

        $service = new GitHubResolveInstallationIdService($this->http);
        $installationId = $service->resolve(RepoSlugVo::fromString('octocat/Hello-World'), $this->jwt, $this->config);

        self::assertSame(424242, $installationId->getValue());
    }

    #[Test]
    public function resolveThrowsWhenInstallationIdMissing(): void
    {
        $this->http->method('request')->willReturn(['node_id' => 'abc']);

        $this->expectException(GitHubApiException::class);

        (new GitHubResolveInstallationIdService($this->http))->resolve(
            RepoSlugVo::fromString('octocat/Hello-World'),
            $this->jwt,
            $this->config,
        );
    }

    #[Test]
    public function resolvePropagatesNotFoundFromTransport(): void
    {
        // Компонент кидает Infrastructure-исключение GitHubHttpException для 404 —
        // сервис оборачивает его в доменное GitHubApiException с сохранением
        // httpStatus, поэтому isNotFound() по-прежнему true.
        $this->http->method('request')->willThrowException(
            GitHubHttpException::forHttpStatus(404, 'GitHub API error: HTTP 404'),
        );

        $service = new GitHubResolveInstallationIdService($this->http);

        try {
            $service->resolve(RepoSlugVo::fromString('octocat/Hello-World'), $this->jwt, $this->config);
            self::fail('Ожидалось GitHubApiException (404).');
        } catch (GitHubApiException $e) {
            self::assertTrue($e->isNotFound());
            self::assertSame(404, $e->getHttpStatus());
        }
    }

    private function buildConfig(): GitIdentityConfigVo
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
            scopeToRepository: true,
            requestTimeoutSeconds: 30,
        );
    }
}
