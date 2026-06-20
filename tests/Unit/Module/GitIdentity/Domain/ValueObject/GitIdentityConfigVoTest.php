<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\AppIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\PrivateKeyVo;

#[CoversClass(GitIdentityConfigVo::class)]
final class GitIdentityConfigVoTest extends TestCase
{
    private const PEM = "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKg==\n-----END PRIVATE KEY-----\n";

    private function validConfig(): GitIdentityConfigVo
    {
        return $this->build(
            apiBaseUri: 'https://api.github.com',
            githubApiVersion: '2026-03-10',
            userAgent: 'task-orchestrator-git-identity',
            jwtTtlSeconds: 540,
            jwtClockSkewSeconds: 60,
            tokenExpirySafetyMarginSeconds: 60,
            installationIdCacheTtlSeconds: 86400,
            scopeToRepository: true,
            requestTimeoutSeconds: 30,
        );
    }

    /**
     * @param string|null $apiBaseUri
     * @param string|null $githubApiVersion
     * @param string|null $userAgent
     */
    private function build(
        ?string $apiBaseUri = null,
        ?string $githubApiVersion = null,
        ?string $userAgent = null,
        ?int $jwtTtlSeconds = null,
        ?int $jwtClockSkewSeconds = null,
        ?int $tokenExpirySafetyMarginSeconds = null,
        ?int $installationIdCacheTtlSeconds = null,
        ?bool $scopeToRepository = null,
        ?int $requestTimeoutSeconds = null,
    ): GitIdentityConfigVo {
        return new GitIdentityConfigVo(
            appId: new AppIdVo(123456),
            privateKey: new PrivateKeyVo(self::PEM),
            apiBaseUri: $apiBaseUri ?? 'https://api.github.com',
            githubApiVersion: $githubApiVersion ?? '2026-03-10',
            userAgent: $userAgent ?? 'task-orchestrator-git-identity',
            jwtTtlSeconds: $jwtTtlSeconds ?? 540,
            jwtClockSkewSeconds: $jwtClockSkewSeconds ?? 60,
            tokenExpirySafetyMarginSeconds: $tokenExpirySafetyMarginSeconds ?? 60,
            installationIdCacheTtlSeconds: $installationIdCacheTtlSeconds ?? 86400,
            scopeToRepository: $scopeToRepository ?? true,
            requestTimeoutSeconds: $requestTimeoutSeconds ?? 30,
        );
    }

    #[Test]
    public function validConfigExposesAllValues(): void
    {
        $config = $this->validConfig();

        self::assertSame(123456, $config->getAppId()->getValue());
        self::assertSame(self::PEM, $config->getPrivateKey()->getContent());
        self::assertSame('https://api.github.com', $config->getApiBaseUri());
        self::assertSame('2026-03-10', $config->getGitHubApiVersion());
        self::assertSame('task-orchestrator-git-identity', $config->getUserAgent());
        self::assertSame(540, $config->getJwtTtlSeconds());
        self::assertSame(60, $config->getJwtClockSkewSeconds());
        self::assertSame(60, $config->getTokenExpirySafetyMarginSeconds());
        self::assertSame(86400, $config->getInstallationIdCacheTtlSeconds());
        self::assertTrue($config->shouldScopeToRepository());
        self::assertSame(30, $config->getRequestTimeoutSeconds());
    }

    #[Test]
    public function installationIdCacheTtlCanBeNullable(): void
    {
        // build() uses a null-coalescing default, so construct directly to assert nullability.
        $config = new GitIdentityConfigVo(
            appId: new AppIdVo(123456),
            privateKey: new PrivateKeyVo(self::PEM),
            apiBaseUri: 'https://api.github.com',
            githubApiVersion: '2026-03-10',
            userAgent: 'task-orchestrator-git-identity',
            jwtTtlSeconds: 540,
            jwtClockSkewSeconds: 60,
            tokenExpirySafetyMarginSeconds: 60,
            installationIdCacheTtlSeconds: null,
            scopeToRepository: true,
            requestTimeoutSeconds: 30,
        );

        self::assertNull($config->getInstallationIdCacheTtlSeconds());
    }

    #[Test]
    #[DataProvider('invalidConfigurations')]
    public function invalidConfigurationsAreRejected(callable $factory, string $messageFragment): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($messageFragment);

        $factory($this);
    }

    /**
     * @return iterable<string, array{callable(self): GitIdentityConfigVo, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'empty api base uri' => [
            static fn (self $t): GitIdentityConfigVo => $t->build(apiBaseUri: ''),
            'Invalid GitHub API base URI',
        ];
        yield 'non-url api base uri' => [
            static fn (self $t): GitIdentityConfigVo => $t->build(apiBaseUri: 'not-a-url'),
            'Invalid GitHub API base URI',
        ];
        yield 'empty api version' => [
            static fn (self $t): GitIdentityConfigVo => $t->build(githubApiVersion: ''),
            'GitHub API version must not be empty',
        ];
        yield 'empty user agent' => [
            static fn (self $t): GitIdentityConfigVo => $t->build(userAgent: ''),
            'User-Agent must not be empty',
        ];
        yield 'jwt ttl zero' => [
            static fn (self $t): GitIdentityConfigVo => $t->build(jwtTtlSeconds: 0),
            'jwt_ttl_seconds must be in range 1..600',
        ];
        yield 'jwt ttl over 600' => [
            static fn (self $t): GitIdentityConfigVo => $t->build(jwtTtlSeconds: 601),
            'jwt_ttl_seconds must be in range 1..600',
        ];
        yield 'negative clock skew' => [
            static fn (self $t): GitIdentityConfigVo => $t->build(jwtClockSkewSeconds: -1),
            'jwt_clock_skew_seconds must be >= 0',
        ];
        yield 'negative safety margin' => [
            static fn (self $t): GitIdentityConfigVo => $t->build(tokenExpirySafetyMarginSeconds: -1),
            'token_expiry_safety_margin_seconds must be >= 0',
        ];
        yield 'negative installation id ttl' => [
            static fn (self $t): GitIdentityConfigVo => $t->build(installationIdCacheTtlSeconds: -1),
            'installation_id_cache_ttl_seconds must be null or >= 0',
        ];
        yield 'zero request timeout' => [
            static fn (self $t): GitIdentityConfigVo => $t->build(requestTimeoutSeconds: 0),
            'request_timeout_seconds must be >= 1',
        ];
    }

    #[Test]
    public function supportsGitHubEnterpriseBaseUri(): void
    {
        $config = $this->build(apiBaseUri: 'https://github.example.com/api/v3');

        self::assertSame('https://github.example.com/api/v3', $config->getApiBaseUri());
    }

    #[Test]
    public function scopeToRepositoryCanBeDisabled(): void
    {
        $config = $this->build(scopeToRepository: false);

        self::assertFalse($config->shouldScopeToRepository());
    }
}
