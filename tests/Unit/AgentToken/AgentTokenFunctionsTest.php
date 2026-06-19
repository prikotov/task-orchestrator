<?php

declare(strict_types=1);

namespace Tests\Unit\AgentToken;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../bin/lib/agent-token.php';

/**
 * Unit-тесты для функций bin/lib/agent-token.php.
 *
 * Реальные сетевые вызовы запрещены — только фикстурные RSA-ключи.
 */
class AgentTokenFunctionsTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__;

    private string $privateKeyPem;
    private string $publicKeyPem;

    protected function setUp(): void
    {
        $this->privateKeyPem = (string) file_get_contents(
            self::FIXTURE_DIR . '/test-private-key.pem'
        );
        $this->publicKeyPem = (string) file_get_contents(
            self::FIXTURE_DIR . '/test-public-key.pem'
        );
    }

    // ─── base64url_encode / base64url_decode ────────────────────────────────

    public function testBase64UrlEncodeRoundTrip(): void
    {
        $original = 'Hello, World! This is a test with special chars: /+=';
        $encoded = agent_token_base64url_encode($original);
        $decoded = agent_token_base64url_decode($encoded);

        $this->assertSame($original, $decoded);
    }

    public function testBase64UrlEncodeIsUrlSafe(): void
    {
        // Бинарные данные, которые дадут + и / в обычном base64
        $data = "\xff\xef\xfe";
        $encoded = agent_token_base64url_encode($data);

        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
    }

    public function testBase64UrlEncodeEmptyString(): void
    {
        $encoded = agent_token_base64url_encode('');
        $decoded = agent_token_base64url_decode($encoded);

        $this->assertSame('', $decoded);
    }

    public function testBase64UrlDecodeWithPadding(): void
    {
        // Ручной encode без паддинга — decode должен восстановить
        $original = '{"alg":"RS256","typ":"JWT"}';
        $encoded = agent_token_base64url_encode($original);
        $decoded = agent_token_base64url_decode($encoded);

        $this->assertSame($original, $decoded);
    }

    // ─── JWT build ──────────────────────────────────────────────────────────

    public function testBuildJwtReturnsThreePartString(): void
    {
        $jwt = agent_token_build_jwt(123456, $this->privateKeyPem);
        $parts = explode('.', $jwt);

        $this->assertCount(3, $parts, 'JWT must have 3 parts separated by dots');
    }

    public function testBuildJwtHeaderContainsRs256(): void
    {
        $jwt = agent_token_build_jwt(123456, $this->privateKeyPem);
        $header = json_decode(
            agent_token_base64url_decode(explode('.', $jwt)[0]),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('RS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);
    }

    public function testBuildJwtPayloadContainsRequiredClaims(): void
    {
        $appId = 999888;
        $beforeTime = time();
        $jwt = agent_token_build_jwt($appId, $this->privateKeyPem);
        $afterTime = time();

        $payload = json_decode(
            agent_token_base64url_decode(explode('.', $jwt)[1]),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame($appId, $payload['iss']);
        // S-4: iat = now − 60 (clock skew запас)
        $this->assertGreaterThanOrEqual($beforeTime - 60, $payload['iat']);
        $this->assertLessThanOrEqual($afterTime - 60, $payload['iat']);
        // exp = iat + 9 минут
        $this->assertSame($payload['iat'] + 540, $payload['exp']);
    }

    public function testBuildJwtSignatureIsVerifiable(): void
    {
        $jwt = agent_token_build_jwt(123456, $this->privateKeyPem);
        [$headerEncoded, $payloadEncoded, $signatureEncoded] = explode('.', $jwt);

        $dataToVerify = $headerEncoded . '.' . $payloadEncoded;
        $signature = agent_token_base64url_decode($signatureEncoded);

        $publicKey = openssl_pkey_get_public($this->publicKeyPem);
        $this->assertNotFalse($publicKey, 'Public key must be loaded');

        $result = openssl_verify(
            $dataToVerify,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256,
        );

        $this->assertSame(1, $result, 'JWT signature must be valid with public key');
    }

    public function testBuildJwtSignatureFailsWithWrongKey(): void
    {
        $jwt = agent_token_build_jwt(123456, $this->privateKeyPem);
        [$headerEncoded, $payloadEncoded, $signatureEncoded] = explode('.', $jwt);

        // Генерируем другой ключ для верификации — подпись не пройдёт
        $otherKeyPair = openssl_pkey_new();
        $otherPublicKey = openssl_pkey_get_details($otherKeyPair)['key'];
        $publicKey = openssl_pkey_get_public($otherPublicKey);

        $result = openssl_verify(
            $headerEncoded . '.' . $payloadEncoded,
            agent_token_base64url_decode($signatureEncoded),
            $publicKey,
            OPENSSL_ALGO_SHA256,
        );

        $this->assertSame(0, $result, 'JWT signature must NOT verify with wrong key');
    }

    public function testBuildJwtThrowsOnInvalidPem(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to load private key');

        agent_token_build_jwt(1, 'not-a-valid-pem-key');
    }

    // ─── parse_repo_argument ────────────────────────────────────────────────

    public function testParseRepoArgumentValid(): void
    {
        $result = agent_token_parse_repo_argument('prikotov/task-orchestrator');

        $this->assertSame('prikotov', $result['owner']);
        $this->assertSame('task-orchestrator', $result['repo']);
    }

    public function testParseRepoArgumentWithDots(): void
    {
        $result = agent_token_parse_repo_argument('my-org/my.repo.name');

        $this->assertSame('my-org', $result['owner']);
        $this->assertSame('my.repo.name', $result['repo']);
    }

    public function testParseRepoArgumentMissingOwner(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        agent_token_parse_repo_argument('/repo-only');
    }

    public function testParseRepoArgumentMissingRepo(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        agent_token_parse_repo_argument('owner-only/');
    }

    public function testParseRepoArgumentNoSlash(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        agent_token_parse_repo_argument('noslash');
    }

    public function testParseRepoArgumentEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        agent_token_parse_repo_argument('');
    }

    // ─── truncate_for_error ─────────────────────────────────────────────────

    public function testTruncateForErrorShortBody(): void
    {
        $body = '{"message":"Not Found"}';
        $result = agent_token_truncate_for_error($body);

        $this->assertSame($body, $result);
    }

    public function testTruncateForErrorLongBody(): void
    {
        $body = str_repeat('a', 300);
        $result = agent_token_truncate_for_error($body, 100);

        $this->assertSame(103, strlen($result)); // 100 chars + '...'
        $this->assertStringEndsWith('...', $result);
    }

    // ─── cleanup_openssl_error ──────────────────────────────────────────────

    public function testCleanupOpenSslErrorReturnsMessage(): void
    {
        // Перед вызовом генерируем ошибку, чтобы openssl_error_string что-то вернул
        openssl_pkey_get_private('invalid-key-content');
        $msg = agent_token_cleanup_openssl_error();

        $this->assertNotEmpty($msg);
        $this->assertLessThanOrEqual(123, strlen($msg)); // 120 + '...'
    }

    public function testCleanupOpenSslErrorStripsPemBlocks(): void
    {
        // CR#1: Имитируем сообщение OpenSSL, содержащее PEM-фрагмент ключа.
        // Проверяем defence-in-depth: PEM-блоки вырезаются + жёсткая обрезка.
        openssl_pkey_get_private('invalid-key-content');

        $msg = agent_token_cleanup_openssl_error();

        // В любом случае: результат не должен содержать PEM-фрагменты
        $this->assertStringNotContainsString('PRIVATE KEY', $msg);
        $this->assertStringNotContainsString('BEGIN', $msg);
        // Проверяем, что нет base64-блока ключа (длинная строка base64 без пробелов)
        $this->assertDoesNotMatchRegularExpression(
            '/[A-Za-z0-9+\/]{40,}/',
            $msg,
            'OpenSSL error message must not contain base64 key fragments'
        );
    }

    // ─── redact_message (N-1) ───────────────────────────────────────────────

    public function testRedactMessageStripsAllSensitiveParts(): void
    {
        // N-1: Прямой вызов чистой функции agent_token_redact_message с синтетическим
        // сообщением из фикстуры (throwaway PEM-фрагменты, не секреты).
        $syntheticMsg = (string) file_get_contents(
            self::FIXTURE_DIR . '/fixtures/synthetic-pem-message.txt'
        );

        $result = agent_token_redact_message($syntheticMsg);

        // (a) Незакрытый PEM-заголовок — вырезан
        $this->assertStringNotContainsString('PRIVATE KEY', $result);
        $this->assertStringNotContainsString('BEGIN', $result);
        // (b) Длинный base64-блок (64 символа) — вырезан
        $this->assertStringNotContainsString('END', $result);
        $this->assertDoesNotMatchRegularExpression(
            '/[A-Za-z0-9+\/]{64,}/',
            $result,
            'Redacted message must not contain base64 fragments >= 64 chars',
        );
    }

    // ─── installation_id cache invalidation (N-3) ──────────────────────────

    public function testInvalidateInstallationIdCacheRemovesFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/agent-token-test-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        agent_token_write_installation_id_cache('owner', 'repo', 12345, $tmpDir);
        $this->assertSame(12345, agent_token_read_installation_id_cache('owner', 'repo', $tmpDir));

        agent_token_invalidate_installation_id_cache('owner', 'repo', $tmpDir);
        $this->assertNull(agent_token_read_installation_id_cache('owner', 'repo', $tmpDir));

        @rmdir($tmpDir);
    }

    // ─── Cache logic ────────────────────────────────────────────────────────

    public function testCachePathReturnsPathWithInstallationId(): void
    {
        $path = agent_token_cache_path(42);

        $this->assertStringContainsString('42.json', $path);
        $this->assertStringContainsString('agent-token', $path);
    }

    public function testCachePathWithCustomCacheDir(): void
    {
        $path = agent_token_cache_path(42, '/tmp/my-cache');

        $this->assertStringContainsString('/tmp/my-cache', $path);
        $this->assertStringContainsString('42.json', $path);
    }

    public function testWriteAndReadCacheValidToken(): void
    {
        $tmpDir = sys_get_temp_dir() . '/agent-token-test-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);

        // CR#3+CR#5: Реальный вызов write_cache → read_cache через параметр cacheDir
        agent_token_write_cache(1, [
            'token' => 'ghs_testToken123',
            'expires_at' => $expiresAt,
        ], $tmpDir);

        $result = agent_token_read_cache(1, $tmpDir);

        $this->assertNotNull($result);
        $this->assertSame('ghs_testToken123', $result['token']);
        $this->assertSame($expiresAt, $result['expires_at']);
        $this->assertSame(1, $result['installation_id']);

        // Убираемся
        array_map('unlink', glob($tmpDir . '/*'));
        @rmdir($tmpDir);
    }

    public function testReadCacheReturnsNullForNonExistentFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/agent-token-test-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        $result = agent_token_read_cache(99999, $tmpDir);

        $this->assertNull($result);

        @rmdir($tmpDir);
    }

    public function testReadCacheReturnsNullForCorruptedCacheFile(): void
    {
        // CR-1: Повреждённый кеш-файл (не JSON) → read_cache возвращает null (cache-miss)
        $tmpDir = sys_get_temp_dir() . '/agent-token-test-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        $path = agent_token_cache_path(1, $tmpDir);
        file_put_contents($path, '{broken json content');

        $result = agent_token_read_cache(1, $tmpDir);

        $this->assertNull($result);

        // Убираемся
        array_map('unlink', glob($tmpDir . '/*'));
        @rmdir($tmpDir);
    }

    public function testReadCacheReturnsNullForMissingKeys(): void
    {
        // CR-1: Валидный JSON, но без ключа token или expires_at → null
        $tmpDir = sys_get_temp_dir() . '/agent-token-test-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        $path = agent_token_cache_path(1, $tmpDir);
        file_put_contents($path, json_encode(['foo' => 'bar']));

        $result = agent_token_read_cache(1, $tmpDir);

        $this->assertNull($result);

        array_map('unlink', glob($tmpDir . '/*'));
        @rmdir($tmpDir);
    }

    // ─── installation_id cache (CR-2) ───────────────────────────────────────

    public function testInstallationIdCachePathContainsOwnerRepo(): void
    {
        $path = agent_token_installation_id_cache_path('prikotov', 'task-orchestrator');

        $this->assertStringContainsString('prikotov_task-orchestrator.installation', $path);
    }

    public function testInstallationIdCachePathWithCustomCacheDir(): void
    {
        $path = agent_token_installation_id_cache_path('org', 'repo', '/tmp/test-cache');

        $this->assertStringContainsString('/tmp/test-cache', $path);
        $this->assertStringContainsString('org_repo.installation', $path);
    }

    public function testWriteAndReadInstallationIdCache(): void
    {
        $tmpDir = sys_get_temp_dir() . '/agent-token-test-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        agent_token_write_installation_id_cache('owner', 'repo', 12345, $tmpDir);
        $result = agent_token_read_installation_id_cache('owner', 'repo', $tmpDir);

        $this->assertSame(12345, $result);

        array_map('unlink', glob($tmpDir . '/*'));
        @rmdir($tmpDir);
    }

    public function testReadInstallationIdCacheReturnsNullForNonExistent(): void
    {
        $tmpDir = sys_get_temp_dir() . '/agent-token-test-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        $result = agent_token_read_installation_id_cache('no', 'cache', $tmpDir);

        $this->assertNull($result);

        @rmdir($tmpDir);
    }

    public function testReadInstallationIdCacheReturnsNullForCorruptedFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/agent-token-test-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        $path = agent_token_installation_id_cache_path('owner', 'repo', $tmpDir);
        file_put_contents($path, 'not-a-number-xyz');

        $result = agent_token_read_installation_id_cache('owner', 'repo', $tmpDir);

        $this->assertNull($result);

        array_map('unlink', glob($tmpDir . '/*'));
        @rmdir($tmpDir);
    }

    public function testReadCacheReturnsNullForExpiredToken(): void
    {
        $tmpDir = sys_get_temp_dir() . '/agent-token-test-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        // expires_at в прошлом — точно протух (TTL-запас 60 сек учитывается в read_cache)
        $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() - 120);

        agent_token_write_cache(7, [
            'token' => 'ghs_expired',
            'expires_at' => $expiresAt,
        ], $tmpDir);

        // Реальный вызов read_cache — должен вернуть null для протухшего
        $result = agent_token_read_cache(7, $tmpDir);

        $this->assertNull($result);

        // Убираемся
        array_map('unlink', glob($tmpDir . '/*'));
        @rmdir($tmpDir);
    }

    // ─── agent_token_obtain cache-reuse (N-3) ───────────────────────────────

    public function testObtainReturnsCachedTokenWithoutNetwork(): void
    {
        // N-3: Проверяем, что agent_token_obtain с закешированным валидным токеном
        // возвращает его без сетевых вызовов (cache hit → немедленный возврат).
        $tmpDir = sys_get_temp_dir() . '/agent-token-test-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        $pemFile = $tmpDir . '/test-key.pem';
        copy(self::FIXTURE_DIR . '/test-private-key.pem', $pemFile);
        chmod($pemFile, 0600);

        putenv("AGENT_PRIVATE_KEY_PATH={$pemFile}");
        putenv('AGENT_APP_ID=42');

        $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);

        agent_token_write_installation_id_cache('owner', 'repo', 99999, $tmpDir);
        agent_token_write_cache(99999, [
            'token' => 'ghs_cachedTokenNoNetwork',
            'expires_at' => $expiresAt,
        ], $tmpDir);

        try {
            $result = agent_token_obtain('owner', 'repo', $tmpDir);

            $this->assertSame('ghs_cachedTokenNoNetwork', $result['token']);
            $this->assertSame($expiresAt, $result['expires_at']);
            $this->assertSame(99999, $result['installation_id']);
        } finally {
            putenv('AGENT_PRIVATE_KEY_PATH=');
            putenv('AGENT_APP_ID=');
            array_map('unlink', glob($tmpDir . '/*'));
            @rmdir($tmpDir);
        }
    }

    // ─── load_configuration ─────────────────────────────────────────────────

    public function testLoadConfigurationFailsWithoutEnvOrFiles(): void
    {
        // Убираем env-переменные, чтобы проверить fallback
        putenv('AGENT_PRIVATE_KEY_PATH=');
        putenv('AGENT_APP_ID=');

        // С household HOME=tmpdir, где нет ~/.config/prikotov-agent/
        $tmpHome = sys_get_temp_dir() . '/agent-token-home-' . uniqid();
        mkdir($tmpHome, 0700, recursive: true);
        putenv("HOME={$tmpHome}");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PEM private key not found');

        try {
            agent_token_load_configuration();
        } finally {
            // Восстанавливаем
            array_map('unlink', glob($tmpHome . '/*'));
            @rmdir($tmpHome);
        }
    }

    public function testLoadConfigurationWithEnvVars(): void
    {
        $tmpDir = sys_get_temp_dir() . '/agent-token-cfg-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        // Создаём временный PEM с правильными правами
        $pemFile = $tmpDir . '/test-key.pem';
        copy(self::FIXTURE_DIR . '/test-private-key.pem', $pemFile);
        chmod($pemFile, 0600);

        putenv("AGENT_PRIVATE_KEY_PATH={$pemFile}");
        putenv('AGENT_APP_ID=42');

        $config = agent_token_load_configuration();

        $this->assertSame(42, $config['appId']);
        $this->assertStringContainsString('test-key.pem', $config['pemPath']);
        $this->assertNotEmpty($config['pemContent']);

        // Убираемся
        putenv('AGENT_PRIVATE_KEY_PATH=');
        putenv('AGENT_APP_ID=');
        array_map('unlink', glob($tmpDir . '/*'));
        @rmdir($tmpDir);
    }

    public function testLoadConfigurationRejectsInsecurePermissions(): void
    {
        $tmpDir = sys_get_temp_dir() . '/agent-token-cfg-' . uniqid();
        mkdir($tmpDir, 0700, recursive: true);

        $pemFile = $tmpDir . '/test-key.pem';
        copy(self::FIXTURE_DIR . '/test-private-key.pem', $pemFile);
        chmod($pemFile, 0644); // Небезопасно!

        // CR#9: Проверяем, что chmod реально применился (volume mount в CI может игнорировать)
        $actualPerms = fileperms($pemFile);
        if ($actualPerms !== false && ($actualPerms & 0o077) === 0) {
            // chmod не сработал — пропускаем тест в CI
            $this->markTestSkipped(
                'chmod(0644) had no effect (likely a volume mount without permission control).'
            );
        }

        putenv("AGENT_PRIVATE_KEY_PATH={$pemFile}");
        putenv('AGENT_APP_ID=42');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('insecure permissions');

        try {
            agent_token_load_configuration();
        } finally {
            putenv('AGENT_PRIVATE_KEY_PATH=');
            putenv('AGENT_APP_ID=');
            array_map('unlink', glob($tmpDir . '/*'));
            @rmdir($tmpDir);
        }
    }

    // ─── Full JWT round-trip (integration-level, но без сети) ───────────────

    public function testFullJwtRoundTripBuildAndVerify(): void
    {
        $appId = 777888;

        $jwt = agent_token_build_jwt($appId, $this->privateKeyPem);
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        // Декодируем payload
        $payload = json_decode(
            agent_token_base64url_decode($parts[1]),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame($appId, $payload['iss']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertSame($payload['iat'] + 540, $payload['exp']);

        // Верифицируем подпись публичным ключом
        $publicKey = openssl_pkey_get_public($this->publicKeyPem);
        $this->assertNotFalse($publicKey);

        $verified = openssl_verify(
            $parts[0] . '.' . $parts[1],
            agent_token_base64url_decode($parts[2]),
            $publicKey,
            OPENSSL_ALGO_SHA256,
        );

        $this->assertSame(1, $verified);
    }
}
