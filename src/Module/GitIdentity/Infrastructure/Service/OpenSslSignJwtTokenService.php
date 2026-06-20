<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service;

use DateTimeImmutable;
use JsonException;
use Override;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\SignJwtTokenServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;

/**
 * Подписчик App JWT (RS256) через ext-openssl.
 *
 * Без сторонних зависимостей. Claims (контракт C):
 *   - iat = now - clock_skew (бэкдейтинг против NTP drift);
 *   - exp = iat + jwt_ttl;
 *   - iss = app_id.
 *
 * Безопасность: PEM-содержимое ключа никогда не попадает в сообщения исключений —
 * ошибки OpenSSL дренируются и очищаются через {@see drainOpenSSLError()}.
 */
final class OpenSslSignJwtTokenService implements SignJwtTokenServiceInterface
{
    #[Override]
    public function sign(GitIdentityConfigVo $config, DateTimeImmutable $now): JwtTokenVo
    {
        $iat = $now->getTimestamp() - $config->getJwtClockSkewSeconds();
        $exp = $iat + $config->getJwtTtlSeconds();
        $appId = $config->getAppId()->getValue();

        try {
            $header = $this->base64urlEncode(
                json_encode(
                    ['alg' => 'RS256', 'typ' => 'JWT'],
                    JSON_THROW_ON_ERROR,
                ),
            );
            $payload = $this->base64urlEncode(
                json_encode(
                    ['iat' => $iat, 'exp' => $exp, 'iss' => $appId],
                    JSON_THROW_ON_ERROR,
                ),
            );
        } catch (JsonException $e) {
            throw new InvalidConfigurationException('Failed to encode JWT payload.', 0, $e);
        }

        $signingInput = $header . '.' . $payload;

        $signature = $this->signWithOpenSsl($signingInput, $config);

        $jwt = $signingInput . '.' . $this->base64urlEncode($signature);

        return new JwtTokenVo($jwt, (new DateTimeImmutable())->setTimestamp($exp));
    }

    private function signWithOpenSsl(string $signingInput, GitIdentityConfigVo $config): string
    {
        // Сбрасываем очередь ошибок OpenSSL перед операцией, чтобы
        // последующий drain возвращал только актуальные ошибки.
        while (openssl_error_string() !== false) {
            // drain
        }

        $privateKey = @openssl_pkey_get_private($config->getPrivateKey()->getContent());
        if ($privateKey === false) {
            throw new InvalidConfigurationException(
                'Failed to load GitHub App private key: ' . self::drainOpenSSLError(),
            );
        }

        $signature = '';
        $ok = @openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if ($ok === false || $signature === '') {
            throw new InvalidConfigurationException(
                'Failed to sign JWT: ' . self::drainOpenSSLError(),
            );
        }

        return $signature;
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Дренирует очередь ошибок OpenSSL и возвращает очищенное сообщение.
     *
     * Defence-in-depth: OpenSSL иногда включает фрагменты ключа в сообщения —
     * вырезаем длинные base64-фрагменты и PEM-блоки.
     */
    private static function drainOpenSSLError(): string
    {
        $collected = '';
        while (($err = openssl_error_string()) !== false) {
            $collected .= ($collected !== '' ? '; ' : '') . $err;
        }

        // Вырезаем потенциально секретные фрагменты (PEM-блоки, длинный base64).
        $collected = preg_replace(
            '/-----BEGIN[^-]*PRIVATE KEY-----.*?-----END[^-]*PRIVATE KEY-----/s',
            '[REDACTED]',
            $collected,
        ) ?? $collected;
        $collected = preg_replace('/[A-Za-z0-9+\/]{64,}/', '[REDACTED]', $collected) ?? $collected;

        if (mb_strlen($collected) > 160) {
            $collected = mb_substr($collected, 0, 160) . '...';
        }

        return $collected !== '' ? $collected : 'unknown OpenSSL error';
    }
}
