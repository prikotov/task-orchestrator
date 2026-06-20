<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Infrastructure\Service;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\AppIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\PrivateKeyVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\GitHubRequestInstallationTokenService;

/**
 * Unit-тесты {@see GitHubRequestInstallationTokenService}.
 *
 * GitHub-эндпоинт выпуска installation access token — `POST /app/installations/
 * {installation_id}/access_tokens` (подчёркивание, без слэша). Тест-шпион на
 * транспорт через {@see RecordingHttpStreamWrapper} фиксирует формируемый URL
 * и проверяет, что он оканчивается на `/access_tokens`.
 *
 * Сверка с GitHub REST API: Create an installation access token for an app.
 *
 * @see RecordingHttpStreamWrapper
 */
#[CoversClass(GitHubRequestInstallationTokenService::class)]
final class GitHubRequestInstallationTokenServiceTest extends TestCase
{
    private const string FIXTURE_PEM = __DIR__ . '/../../fixtures/test-private-key.pem';

    private const string SCHEME = 'gitmock';

    private GitIdentityConfigVo $config;

    private JwtTokenVo $jwt;

    private InstallationIdVo $installationId;

    private RepoSlugVo $repoSlug;

    #[\Override]
    protected function setUp(): void
    {
        $this->installationId = new InstallationIdVo(424242);
        $this->repoSlug = RepoSlugVo::fromString('octocat/Hello-World');
        $this->jwt = new JwtTokenVo('header.payload.signature', new DateTimeImmutable('+1 minute'));
        $this->config = $this->buildConfig('gitmock://api.github.test', true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::SCHEME);
        }
        RecordingHttpStreamWrapper::reset();
    }

    #[Test]
    public function requestTargetsAccessTokensEndpointWithoutSlash(): void
    {
        $this->registerWrapper($this->validResponseBody());

        $service = new GitHubRequestInstallationTokenService();
        $token = $service->request($this->installationId, $this->jwt, $this->config, $this->repoSlug);

        self::assertNotNull(RecordingHttpStreamWrapper::$lastUrl, 'HTTP-запрос не был выполнен.');
        self::assertStringEndsWith(
            '/app/installations/424242/access_tokens',
            RecordingHttpStreamWrapper::$lastUrl,
            'URL должен оканчиваться на /access_tokens (подчёркивание, без слэша).',
        );
        // Подстраховка: устаревший вариант со слэшем никогда не должен сформироваться.
        self::assertStringNotContainsString('/access/tokens', RecordingHttpStreamWrapper::$lastUrl);

        self::assertSame('ghs_secret_token', $token->getToken());
    }

    #[Test]
    public function requestWithScopeDisabledStillUsesCorrectEndpoint(): void
    {
        $this->config = $this->buildConfig('gitmock://api.github.test', false);
        $this->registerWrapper($this->validResponseBody());

        $service = new GitHubRequestInstallationTokenService();
        $service->request($this->installationId, $this->jwt, $this->config, $this->repoSlug);

        self::assertStringEndsWith('/access_tokens', RecordingHttpStreamWrapper::$lastUrl);
        self::assertStringNotContainsString('/access/tokens', RecordingHttpStreamWrapper::$lastUrl);
    }

    #[Test]
    public function requestWithoutTokenInResponseThrows(): void
    {
        $this->registerWrapper('{"expires_at":"' . $this->futureIso() . '"}');

        $this->expectException(GitHubApiException::class);

        (new GitHubRequestInstallationTokenService())->request(
            $this->installationId,
            $this->jwt,
            $this->config,
            $this->repoSlug,
        );
    }

    private function registerWrapper(string $responseBody): void
    {
        RecordingHttpStreamWrapper::reset($responseBody);
        stream_wrapper_register(self::SCHEME, RecordingHttpStreamWrapper::class);
    }

    private function validResponseBody(): string
    {
        return '{"token":"ghs_secret_token","expires_at":"' . $this->futureIso() . '"}';
    }

    private function futureIso(): string
    {
        return (new DateTimeImmutable('+1 hour'))->format(DateTimeImmutable::ATOM);
    }

    private function buildConfig(string $apiBaseUri, bool $scopeToRepository): GitIdentityConfigVo
    {
        return new GitIdentityConfigVo(
            appId: new AppIdVo(123456),
            privateKey: new PrivateKeyVo((string) file_get_contents(self::FIXTURE_PEM)),
            apiBaseUri: $apiBaseUri,
            githubApiVersion: '2026-03-10',
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
