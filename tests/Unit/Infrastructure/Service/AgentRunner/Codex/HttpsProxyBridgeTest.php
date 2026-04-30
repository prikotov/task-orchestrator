<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Codex;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\HttpsProxyBridge;

#[CoversClass(HttpsProxyBridge::class)]
final class HttpsProxyBridgeTest extends TestCase
{
    // ──── parseUpstreamUrl: валидные HTTPS URL ──────────────────────────

    #[Test]
    public function parseUpstreamUrlWithFullCredentialsReturnsComponents(): void
    {
        $result = HttpsProxyBridge::parseUpstreamUrl('https://user:pass@proxy.example.com:8080');

        self::assertNotNull($result);
        self::assertSame('proxy.example.com', $result['host']);
        self::assertSame(8080, $result['port']);
        self::assertSame('user', $result['user']);
        self::assertSame('pass', $result['pass']);
    }

    #[Test]
    public function parseUpstreamUrlWithoutCredentialsReturnsEmptyUserPass(): void
    {
        $result = HttpsProxyBridge::parseUpstreamUrl('https://proxy.example.com:33090');

        self::assertNotNull($result);
        self::assertSame('proxy.example.com', $result['host']);
        self::assertSame(33090, $result['port']);
        self::assertSame('', $result['user']);
        self::assertSame('', $result['pass']);
    }

    #[Test]
    public function parseUpstreamUrlWithUserOnlyReturnsEmptyPass(): void
    {
        $result = HttpsProxyBridge::parseUpstreamUrl('https://myuser@proxy.example.com:3128');

        self::assertNotNull($result);
        self::assertSame('myuser', $result['user']);
        self::assertSame('', $result['pass']);
    }

    #[Test]
    public function parseUpstreamUrlDecodesPercentEncodedCredentials(): void
    {
        $result = HttpsProxyBridge::parseUpstreamUrl('https://user%40domain:p%40ssw0rd@proxy.example.com:8080');

        self::assertNotNull($result);
        self::assertSame('user@domain', $result['user']);
        self::assertSame('p@ssw0rd', $result['pass']);
    }

    #[Test]
    public function parseUpstreamUrlDecodesUrlEncodedSpecialChars(): void
    {
        $result = HttpsProxyBridge::parseUpstreamUrl('https://user%3Aname:p%2Fs%23w0rd@proxy.example.com:8080');

        self::assertNotNull($result);
        self::assertSame('user:name', $result['user']);
        self::assertSame('p/s#w0rd', $result['pass']);
    }

    // ──── parseUpstreamUrl: невалидные URL → null ────────────────────────

    #[Test]
    public function parseUpstreamUrlReturnsNullForHttpScheme(): void
    {
        $result = HttpsProxyBridge::parseUpstreamUrl('http://proxy.example.com:8080');

        self::assertNull($result);
    }

    #[Test]
    public function parseUpstreamUrlReturnsNullForEmptyString(): void
    {
        $result = HttpsProxyBridge::parseUpstreamUrl('');

        self::assertNull($result);
    }

    #[Test]
    public function parseUpstreamUrlReturnsNullForNoPort(): void
    {
        $result = HttpsProxyBridge::parseUpstreamUrl('https://proxy.example.com');

        self::assertNull($result);
    }

    #[Test]
    public function parseUpstreamUrlReturnsNullForNoHost(): void
    {
        $result = HttpsProxyBridge::parseUpstreamUrl('https://:8080');

        self::assertNull($result);
    }

    #[Test]
    public function parseUpstreamUrlReturnsNullForFtpScheme(): void
    {
        $result = HttpsProxyBridge::parseUpstreamUrl('ftp://proxy.example.com:21');

        self::assertNull($result);
    }

    #[Test]
    public function parseUpstreamUrlReturnsNullForMalformedUrl(): void
    {
        $result = HttpsProxyBridge::parseUpstreamUrl('not a url at all');

        self::assertNull($result);
    }

    // ──── Constructor: валидация URL ────────────────────────────────────

    #[Test]
    public function constructorAcceptsValidHttpsUrl(): void
    {
        $bridge = new HttpsProxyBridge('https://proxy.example.com:8080');

        // Не бросает исключение — уже успех
        self::assertFalse($bridge->isRunning());
    }

    #[Test]
    public function constructorThrowsForNonHttpsUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid upstream HTTPS proxy URL');

        new HttpsProxyBridge('http://proxy.example.com:8080');
    }

    #[Test]
    public function constructorThrowsForEmptyUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid upstream HTTPS proxy URL');

        new HttpsProxyBridge('');
    }

    // ──── start / stop lifecycle ────────────────────────────────────────

    #[Test]
    public function startLaunchesBridgeAndReturnsLocalUrl(): void
    {
        $bridge = new HttpsProxyBridge('https://proxy.example.com:8080');

        try {
            $localUrl = $bridge->start();

            self::assertMatchesRegularExpression('#^http://127\.0\.0\.1:\d+$#', $localUrl);
            self::assertTrue($bridge->isRunning());
            self::assertSame($localUrl, $bridge->getLocalProxyUrl());
        } finally {
            $bridge->stop();
        }
    }

    #[Test]
    public function stopTerminatesBridgeProcess(): void
    {
        $bridge = new HttpsProxyBridge('https://proxy.example.com:8080');
        $bridge->start();

        self::assertTrue($bridge->isRunning());

        $bridge->stop();

        self::assertFalse($bridge->isRunning());
        self::assertSame('', $bridge->getLocalProxyUrl());
    }

    #[Test]
    public function stopIsIdempotent(): void
    {
        $bridge = new HttpsProxyBridge('https://proxy.example.com:8080');

        // stop() на не запущенном мосту — не бросает исключений
        $bridge->stop();
        $bridge->stop();

        self::assertFalse($bridge->isRunning());
    }

    #[Test]
    public function stopAfterStopIsSafe(): void
    {
        $bridge = new HttpsProxyBridge('https://proxy.example.com:8080');
        $bridge->start();
        $bridge->stop();

        // Повторный stop() после остановки
        $bridge->stop();

        self::assertFalse($bridge->isRunning());
    }

    #[Test]
    public function startReturnsSameUrlIfAlreadyRunning(): void
    {
        $bridge = new HttpsProxyBridge('https://proxy.example.com:8080');

        try {
            $url1 = $bridge->start();
            $url2 = $bridge->start();

            self::assertSame($url1, $url2);
        } finally {
            $bridge->stop();
        }
    }

    #[Test]
    public function startAssignsDifferentPortsForDifferentInstances(): void
    {
        $bridge1 = new HttpsProxyBridge('https://proxy.example.com:8080');
        $bridge2 = new HttpsProxyBridge('https://proxy.example.com:8080');

        try {
            $url1 = $bridge1->start();
            $url2 = $bridge2->start();

            self::assertNotSame($url1, $url2);
        } finally {
            $bridge1->stop();
            $bridge2->stop();
        }
    }

    #[Test]
    public function isRunningReturnsFalseBeforeStart(): void
    {
        $bridge = new HttpsProxyBridge('https://proxy.example.com:8080');

        self::assertFalse($bridge->isRunning());
    }

    #[Test]
    public function getLocalProxyUrlReturnsEmptyBeforeStart(): void
    {
        $bridge = new HttpsProxyBridge('https://proxy.example.com:8080');

        self::assertSame('', $bridge->getLocalProxyUrl());
    }

    // ──── Bridge script содержит ожидаемые параметры ────────────────────

    #[Test]
    public function bridgeProcessAcceptsConnectRequest(): void
    {
        $bridge = new HttpsProxyBridge('https://user:pass@proxy.example.com:8080');

        try {
            $localUrl = $bridge->start();
            self::assertMatchesRegularExpression('#^http://127\.0\.0\.1:\d+$#', $localUrl);

            // Подключаемся к мосту и отправляем CONNECT
            $parsed = parse_url($localUrl);
            $socket = @stream_socket_client(
                sprintf('tcp://%s:%d', $parsed['host'], $parsed['port']),
                $errno,
                $errstr,
                5,
            );

            self::assertNotFalse($socket, "Failed to connect to bridge: $errstr ($errno)");

            // Отправляем CONNECT запрос
            fwrite($socket, "CONNECT api.openai.com:443 HTTP/1.1\r\nHost: api.openai.com:443\r\n\r\n");

            // Читаем ответ — ожидаем 502 (upstream недоступен в тесте),
            // но важно что мост принял CONNECT и ответил
            stream_set_blocking($socket, false);
            $response = '';
            $start = microtime(true);
            while ((microtime(true) - $start) < 3.0) {
                $chunk = fread($socket, 8192);
                if ($chunk !== false && $chunk !== '') {
                    $response .= $chunk;
                    if (str_contains($response, "\r\n\r\n")) {
                        break;
                    }
                }
                usleep(10000);
            }

            // Мост должен ответить HTTP (200 или 502 — upstream недоступен в unit-тесте)
            self::assertMatchesRegularExpression('/^HTTP\/1\.1 \d{3}/', $response);

            fclose($socket);
        } finally {
            $bridge->stop();
        }
    }

    // ──── Custom connect timeout ────────────────────────────────────────

    #[Test]
    public function constructorAcceptsCustomConnectTimeout(): void
    {
        $bridge = new HttpsProxyBridge('https://proxy.example.com:8080', 30);

        try {
            $localUrl = $bridge->start();
            self::assertMatchesRegularExpression('#^http://127\.0\.0\.1:\d+$#', $localUrl);
        } finally {
            $bridge->stop();
        }
    }
}
