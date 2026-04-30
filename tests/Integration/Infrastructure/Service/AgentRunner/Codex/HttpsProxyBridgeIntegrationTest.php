<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Service\AgentRunner\Codex;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\HttpsProxyBridge;

/**
 * Integration-тест для HttpsProxyBridge.
 *
 * Проверяет полный цикл: мост принимает CONNECT от клиента,
 * устанавливает TLS к upstream-прокси, пробрасывает данные
 * через туннель к целевому HTTP-серверу и обратно.
 *
 * Все серверы (target HTTP, upstream TLS-proxy, bridge) запускаются
 * локально в самом тесте. Внешних зависимостей нет.
 */
#[Group('integration')]
final class HttpsProxyBridgeIntegrationTest extends TestCase
{
    private string $tempDir = '';

    /** @var resource|null Target HTTP server socket */
    private $targetServer = null;

    /** @var resource|null Upstream TLS proxy process */
    private $tlsProxyProcess = null;

    private ?HttpsProxyBridge $bridge = null;

    private int $targetPort = 0;

    private int $tlsProxyPort = 0;

    protected function setUp(): void
    {
        // Генерируем self-signed сертификат для upstream TLS-прокси
        $this->tempDir = sys_get_temp_dir() . '/bridge_test_' . uniqid();
        mkdir($this->tempDir, 0700, true);

        $certFile = $this->tempDir . '/cert.pem';
        $keyFile = $this->tempDir . '/key.pem';

        shell_exec(
            "openssl req -x509 -newkey rsa:2048 -keyout {$keyFile} -out {$certFile}"
            . " -days 1 -nodes -subj '/CN=localhost' 2>/dev/null",
        );

        self::assertFileExists($certFile, 'Failed to generate self-signed certificate');

        // 1. Запускаем целевой HTTP-сервер
        $this->targetServer = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($this->targetServer, "Cannot start target server: $errstr ($errno)");
        $this->targetPort = $this->extractPort($this->targetServer);

        // 2. Запускаем upstream TLS-прокси
        $this->tlsProxyPort = $this->startTlsProxy($certFile, $keyFile);

        // 3. Настраиваем bridge для доверия нашему сертификату
        putenv("BRIDGE_TLS_VERIFY=0");

        // 4. Запускаем мост
        $this->bridge = new HttpsProxyBridge(
            upstreamProxyUrl: "https://127.0.0.1:{$this->tlsProxyPort}",
        );
        $this->bridge->start();
    }

    protected function tearDown(): void
    {
        // Останавливаем мост
        $this->bridge?->stop();
        $this->bridge = null;

        // Убиваем TLS-прокси
        if ($this->tlsProxyProcess !== null && is_resource($this->tlsProxyProcess)) {
            proc_terminate($this->tlsProxyProcess, SIGKILL);
            proc_close($this->tlsProxyProcess);
            $this->tlsProxyProcess = null;
        }

        // Закрываем target сервер
        if ($this->targetServer !== null && is_resource($this->targetServer)) {
            fclose($this->targetServer);
            $this->targetServer = null;
        }

        // Очищаем env
        putenv('BRIDGE_TLS_VERIFY');

        // Удаляем временные файлы
        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            @unlink($this->tempDir . '/cert.pem');
            @unlink($this->tempDir . '/key.pem');
            @rmdir($this->tempDir);
        }
    }

    #[Test]
    public function bridgeTunnelsConnectRequestThroughTlsUpstream(): void
    {
        // Запускаем обработчик target-сервера в фоне
        $targetHandlerPid = $this->forkTargetHandler();

        try {
            // Подключаемся к мосту как клиент
            $localUrl = $this->bridge->getLocalProxyUrl();
            self::assertNotSame('', $localUrl);

            // getLocalProxyUrl возвращает http://127.0.0.1:<port> — подключаемся к TCP
            $client = stream_socket_client(
                str_replace('http://', 'tcp://', $localUrl),
                $errno,
                $errstr,
                5,
            );
            self::assertNotFalse($client, "Cannot connect to bridge: $errstr ($errno)");

            stream_set_blocking($client, true);

            // Отправляем CONNECT-запрос
            $connectRequest = "CONNECT 127.0.0.1:{$this->targetPort} HTTP/1.1\r\n\r\n";
            fwrite($client, $connectRequest);

            // Читаем ответ CONNECT
            $response = $this->readUntil($client, "\r\n\r\n", 5);
            self::assertStringContainsString('200', $response, 'CONNECT should return 200');

            // Пишем HTTP-запрос через туннель
            $httpRequest = "GET /hello HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n";
            fwrite($client, $httpRequest);

            // Читаем HTTP-ответ через туннель
            $httpResponse = $this->readUntil($client, 'HELLO_FROM_TARGET', 5);
            self::assertStringContainsString('200 OK', $httpResponse);
            self::assertStringContainsString('HELLO_FROM_TARGET', $httpResponse);

            fclose($client);
        } finally {
            // Убиваем fork-обработчик если жив
            if ($targetHandlerPid > 0) {
                @posix_kill($targetHandlerPid, SIGKILL);
            }
        }
    }

    // ──── Helpers ────────────────────────────────────────────────────────

    /**
     * Запускает upstream TLS-прокси как отдельный PHP-процесс.
     *
     * Принимает CONNECT, подключается к target, пробрасывает bidirectionally.
     * Скрипт записывается во временный файл для надёжного запуска.
     */
    private function startTlsProxy(string $certFile, string $keyFile): int
    {
        $targetPort = $this->targetPort;
        $scriptFile = $this->tempDir . '/tls_proxy.php';

        // Записываем скрипт прокси в файл — надёжнее чем php -r с длинным скриптом
        file_put_contents($scriptFile, $this->buildTlsProxyScript($certFile, $keyFile, $targetPort));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([PHP_BINARY, $scriptFile], $descriptors, $pipes);
        self::assertNotFalse($process, 'Failed to start TLS proxy process');

        fclose($pipes[0]);

        // Читаем порт из stdout
        stream_set_blocking($pipes[1], false);
        $buffer = '';
        $start = microtime(true);
        while ((microtime(true) - $start) < 5.0) {
            $chunk = fread($pipes[1], 1024);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
                if (preg_match('/^PORT:(\d+)\n/', $buffer, $m)) {
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $this->tlsProxyProcess = $process;

                    return (int) $m[1];
                }
            }
            usleep(10000);
        }

        // diag: прочитать stderr перед тем как упасть
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_terminate($process, SIGKILL);
        proc_close($process);
        self::fail('TLS proxy did not start within timeout. stderr: ' . $stderr);
    }

    /**
     * Генерирует PHP-скрипт upstream TLS-прокси.
     */
    private function buildTlsProxyScript(string $certFile, string $keyFile, int $targetPort): string
    {
        $certFileSafe = addcslashes($certFile, "'");
        $keyFileSafe = addcslashes($keyFile, "'");

        return <<<PHP
<?php

declare(strict_types=1);

\$certFile = '{$certFileSafe}';
\$keyFile = '{$keyFileSafe}';
\$targetPort = {$targetPort};

\$ctx = stream_context_create([
    'ssl' => [
        'local_cert' => \$certFile,
        'local_pk' => \$keyFile,
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);

\$server = @stream_socket_server('ssl://127.0.0.1:0', \$errno, \$errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, \$ctx);
if (\$server === false) {
    fwrite(STDERR, "TLS proxy: cannot start: \$errstr (\$errno)\n");
    exit(1);
}

\$addr = stream_socket_get_name(\$server, false);
\$port = (int) parse_url('tcp://' . (\$addr !== false ? \$addr : ''), PHP_URL_PORT);
echo "PORT:{\$port}\n";
flush();

while (true) {
    \$client = @stream_socket_accept(\$server, 60);
    if (\$client === false) {
        continue;
    }

    stream_set_blocking(\$client, true);

    \$request = '';
    \$start = microtime(true);
    while ((microtime(true) - \$start) < 5.0) {
        \$chunk = fread(\$client, 8192);
        if (\$chunk !== false && \$chunk !== '') {
            \$request .= \$chunk;
            if (str_contains(\$request, "\r\n\r\n")) {
                break;
            }
        }
    }

    if (!preg_match('/^CONNECT\s+([^:]+):(\d+)/i', \$request, \$m)) {
        fwrite(\$client, "HTTP/1.1 400 Bad Request\r\n\r\n");
        @fclose(\$client);
        continue;
    }

    fwrite(\$client, "HTTP/1.1 200 Connection Established\r\n\r\n");

    \$target = @stream_socket_client('tcp://127.0.0.1:' . \$targetPort, \$errno, \$errstr, 5);
    if (\$target === false) {
        @fclose(\$client);
        continue;
    }

    \$startTime = time();
    while (true) {
        if (time() - \$startTime > 30) {
            break;
        }
        \$read = [\$client, \$target];
        \$write = null;
        \$except = null;
        \$changed = @stream_select(\$read, \$write, \$except, 1);
        if (\$changed === false) {
            break;
        }
        if (\$changed === 0) {
            continue;
        }
        foreach (\$read as \$socket) {
            \$data = @fread(\$socket, 65536);
            if (\$data === false || \$data === '') {
                break 2;
            }
            \$dest = (\$socket === \$client) ? \$target : \$client;
            @fwrite(\$dest, \$data);
        }
    }

    @fclose(\$target);
    @fclose(\$client);
}
PHP;
    }

    /**
     * Fork-процесс для обработки запросов к target HTTP-серверу.
     *
     * @return int PID дочернего процесса (0 если в child)
     */
    private function forkTargetHandler(): int
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            self::fail('pcntl_fork failed');
        }

        if ($pid === 0) {
            // Child: обрабатываем одно соединение
            $client = @stream_socket_accept($this->targetServer, 10);
            if ($client !== false) {
                // Читаем HTTP-запрос
                $this->readUntil($client, "\r\n\r\n", 3);

                // Отвечаем
                $body = 'HELLO_FROM_TARGET';
                $response = "HTTP/1.1 200 OK\r\n"
                    . "Content-Type: text/plain\r\n"
                    . "Content-Length: " . strlen($body) . "\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . $body;
                @fwrite($client, $response);
                @fclose($client);
            }
            // Child exit
            exit(0);
        }

        return $pid;
    }

    /**
     * Читает из сокета пока не встретит $delimiter или не истечёт таймаут.
     */
    private function readUntil(mixed $socket, string $delimiter, float $timeoutSecs): string
    {
        $buffer = '';
        $start = microtime(true);
        while ((microtime(true) - $start) < $timeoutSecs) {
            $chunk = @fread($socket, 8192);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
                if (str_contains($buffer, $delimiter)) {
                    return $buffer;
                }
            }
            usleep(10000);
        }

        return $buffer;
    }

    /**
     * Извлекает порт из server socket.
     */
    private function extractPort(mixed $socket): int
    {
        $addr = stream_socket_get_name($socket, false);
        self::assertNotFalse($addr);

        return (int) parse_url('tcp://' . $addr, PHP_URL_PORT);
    }
}
