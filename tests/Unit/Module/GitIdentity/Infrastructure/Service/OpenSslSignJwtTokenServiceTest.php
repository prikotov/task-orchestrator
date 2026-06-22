<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Infrastructure\Service;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\AppIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\PrivateKeyVo;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\OpenSslSignJwtTokenService;

#[CoversClass(OpenSslSignJwtTokenService::class)]
final class OpenSslSignJwtTokenServiceTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../../fixtures';

    private const APP_ID = 999888777;

    private const JWT_TTL = 540;

    private const CLOCK_SKEW = 60;

    private OpenSslSignJwtTokenService $service;

    private string $privateKeyPem;

    private string $publicKeyPem;

    #[\Override]
    protected function setUp(): void
    {
        $this->privateKeyPem = (string) file_get_contents(self::FIXTURE_DIR . '/test-private-key.pem');
        $this->publicKeyPem = (string) file_get_contents(self::FIXTURE_DIR . '/test-public-key.pem');
        $this->service = new OpenSslSignJwtTokenService();
    }

    private function buildConfig(?PrivateKeyVo $privateKey = null, ?AppIdVo $appId = null): GitIdentityConfigVo
    {
        return new GitIdentityConfigVo(
            appId: $appId ?? new AppIdVo(self::APP_ID),
            privateKey: $privateKey ?? new PrivateKeyVo($this->privateKeyPem),
            apiBaseUri: 'https://api.github.com',
            githubApiVersion: '2022-11-28',
            userAgent: 'task-orchestrator-git-identity-test',
            jwtTtlSeconds: self::JWT_TTL,
            jwtClockSkewSeconds: self::CLOCK_SKEW,
            tokenExpirySafetyMarginSeconds: 60,
            installationIdCacheTtlSeconds: 86400,
            scopeToRepository: true,
            requestTimeoutSeconds: 30,
        );
    }

    #[Test]
    public function signProducesThreePartJwt(): void
    {
        $now = new DateTimeImmutable();
        $jwt = $this->service->sign($this->buildConfig(), $now);

        self::assertInstanceOf(JwtTokenVo::class, $jwt);
        $parts = explode('.', $jwt->getValue());
        self::assertCount(3, $parts, 'JWT must consist of header.payload.signature');
        self::assertNotSame('', $parts[0]);
        self::assertNotSame('', $parts[1]);
        self::assertNotSame('', $parts[2]);
    }

    #[Test]
    public function headerDeclaresRs256AndJwtType(): void
    {
        $now = new DateTimeImmutable();
        $jwt = $this->service->sign($this->buildConfig(), $now);

        [$headerEncoded] = explode('.', $jwt->getValue(), 2);
        /** @var array{alg: string, typ: string} $header */
        $header = json_decode($this->base64urlDecode($headerEncoded), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('RS256', $header['alg']);
        self::assertSame('JWT', $header['typ']);
    }

    #[Test]
    public function payloadContainsIatExpAndIssWithClockSkew(): void
    {
        $now = new DateTimeImmutable();
        $jwt = $this->service->sign($this->buildConfig(), $now);

        $parts = explode('.', $jwt->getValue());
        /** @var array{iat: int, exp: int, iss: int} $payload */
        $payload = json_decode($this->base64urlDecode($parts[1]), true, 512, JSON_THROW_ON_ERROR);

        $expectedIat = $now->getTimestamp() - self::CLOCK_SKEW;
        $expectedExp = $expectedIat + self::JWT_TTL;

        self::assertSame($expectedIat, $payload['iat']);
        self::assertSame($expectedExp, $payload['exp']);
        self::assertSame(self::APP_ID, $payload['iss']);
    }

    #[Test]
    public function expiresAtMatchesIatPlusTtl(): void
    {
        $now = new DateTimeImmutable();
        $jwt = $this->service->sign($this->buildConfig(), $now);

        $parts = explode('.', $jwt->getValue());
        /** @var array{exp: int} $payload */
        $payload = json_decode($this->base64urlDecode($parts[1]), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($payload['exp'], $jwt->getExpiresAt()->getTimestamp());
    }

    #[Test]
    public function signatureVerifiesAgainstPublicKey(): void
    {
        $now = new DateTimeImmutable();
        $jwt = $this->service->sign($this->buildConfig(), $now);

        $parts = explode('.', $jwt->getValue());
        $signingInput = $parts[0] . '.' . $parts[1];
        $signature = $this->base64urlDecode($parts[2]);

        $publicKey = openssl_pkey_get_public($this->publicKeyPem);
        self::assertNotFalse($publicKey, 'Test fixture public key must load');

        $valid = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        self::assertSame(1, $valid, 'JWT signature must verify against the fixture public key');
    }

    #[Test]
    public function signatureChangesAcrossSignings(): void
    {
        $now = new DateTimeImmutable();
        $config = $this->buildConfig();

        $a = $this->service->sign($config, $now)->getValue();
        $b = $this->service->sign($config, $now)->getValue();

        // RS256 is deterministic, so two signings with identical input yield identical JWTs;
        // but a different app id MUST change the payload/signature.
        $c = $this->service->sign($this->buildConfig(appId: new AppIdVo(self::APP_ID + 1)), $now)->getValue();

        self::assertSame($a, $b, 'RS256 signing is deterministic for identical input');
        self::assertNotSame($a, $c, 'Different app id must produce a different JWT');
    }

    #[Test]
    public function invalidPrivateKeyThrowsWithoutLeakingContent(): void
    {
        $invalidKey = new PrivateKeyVo(
            (string) file_get_contents(self::FIXTURE_DIR . '/invalid-private-key.pem'),
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/Failed to (load|sign)/');

        try {
            $this->service->sign($this->buildConfig(privateKey: $invalidKey), new DateTimeImmutable());
        } catch (InvalidConfigurationException $e) {
            self::assertStringNotContainsString('BEGIN PRIVATE KEY', $e->getMessage());
            throw $e;
        }
    }

    private function base64urlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);

        return (string) base64_decode($padded, true);
    }
}
