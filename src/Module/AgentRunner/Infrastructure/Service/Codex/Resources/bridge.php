<?php

/**
 * HTTPS→HTTP прокси-мост для CodexAgentRunner.
 *
 * Запускается как отдельный PHP-процесс через proc_open из HttpsProxyBridge.
 * Слушает на 127.0.0.1:0 (ОС назначает свободный порт), принимает HTTP CONNECT-запросы
 * и пересылает их через TLS на upstream HTTPS-прокси.
 *
 * Bidirectional forwarding реализован через pcntl_fork():
 * — родитель: client → upstream (blocking fread/fwrite)
 * — ребёнок:  upstream → client (blocking fread/fwrite)
 *
 * Конфигурация передаётся через environment variables:
 *   BRIDGE_UPSTREAM_HOST   — хост upstream HTTPS-прокси
 *   BRIDGE_UPSTREAM_PORT   — порт upstream HTTPS-прокси
 *   BRIDGE_AUTH_HEADER     — заголовок Proxy-Authorization (пустая строка если не нужен)
 *   BRIDGE_CONNECT_TIMEOUT — таймаут соединения с upstream (секунды)
 *   BRIDGE_HOST            — локальный адрес моста (обычно 127.0.0.1)
 *
 * @see \TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\HttpsProxyBridge
 */

declare(strict_types=1);

$upstreamHost = getenv('BRIDGE_UPSTREAM_HOST');
$upstreamPort = getenv('BRIDGE_UPSTREAM_PORT');
$authHeader = getenv('BRIDGE_AUTH_HEADER');
$connectTimeout = (int) getenv('BRIDGE_CONNECT_TIMEOUT');
$bridgeHost = getenv('BRIDGE_HOST');

if ($upstreamHost === false || $upstreamPort === false || $bridgeHost === false) {
    fwrite(STDERR, "Bridge: missing required environment variables.\n");
    exit(1);
}

if ($connectTimeout <= 0) {
    $connectTimeout = 15;
}

// ─── TCP Server ────────────────────────────────────────────────────────

$server = @stream_socket_server('tcp://' . $bridgeHost . ':0', $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, "Bridge: cannot create server: $errstr ($errno)\n");
    exit(1);
}

$addr = stream_socket_get_name($server, false);
$localPort = (int) parse_url('tcp://' . ($addr !== false ? $addr : ''), PHP_URL_PORT);
echo "PORT:{$localPort}\n";
flush();

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function () use ($server) {
        @fclose($server);
        exit(0);
    });
    pcntl_async_signals(true);
}

// ─── Main accept loop ─────────────────────────────────────────────────

$childPids = [];

while (true) {
    $client = @stream_socket_accept($server, 60);
    if ($client === false) {
        continue;
    }

    // ── Читаем CONNECT-запрос (non-blocking, короткий timeout) ──

    stream_set_blocking($client, false);

    $request = '';
    $start = microtime(true);
    while ((microtime(true) - $start) < 5.0) {
        $chunk = fread($client, 8192);
        if ($chunk !== false && $chunk !== '') {
            $request .= $chunk;
            if (str_contains($request, "\r\n\r\n")) {
                break;
            }
        }
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        usleep(5000);
    }

    if (!preg_match('/^CONNECT\s+([^:]+):(\d+)\s+HTTP/i', $request, $m)) {
        fwrite($client, "HTTP/1.1 400 Bad Request\r\n\r\n");
        @fclose($client);
        continue;
    }

    $targetHost = $m[1];
    $targetPort = (int) $m[2];

    // ── TLS connect к upstream HTTPS-прокси ──

    $verifyTls = getenv('BRIDGE_TLS_VERIFY') !== '0';

    $sslOptions = [
        'verify_peer' => $verifyTls,
        'verify_peer_name' => $verifyTls,
    ];

    $caFile = getenv('BRIDGE_CA_FILE');
    if ($caFile !== false && $caFile !== '' && file_exists($caFile)) {
        $sslOptions['cafile'] = $caFile;
    }

    $ctx = stream_context_create(['ssl' => $sslOptions]);

    $upstream = @stream_socket_client(
        'tls://' . $upstreamHost . ':' . $upstreamPort,
        $errno,
        $errstr,
        $connectTimeout,
        STREAM_CLIENT_CONNECT,
        $ctx,
    );

    if ($upstream === false) {
        fwrite($client, "HTTP/1.1 502 Bad Gateway\r\n\r\n");
        @fclose($client);
        continue;
    }

    // ── Отправляем CONNECT через upstream прокси ──

    $connectRequest = "CONNECT {$targetHost}:{$targetPort} HTTP/1.1\r\n"
        . "Host: {$targetHost}:{$targetPort}\r\n";
    if ($authHeader !== false && $authHeader !== '') {
        $connectRequest .= $authHeader . "\r\n";
    }
    $connectRequest .= "\r\n";

    fwrite($upstream, $connectRequest);

    // Читаем ответ upstream (non-blocking, короткий timeout)
    stream_set_blocking($upstream, false);

    $response = '';
    $start = microtime(true);
    while ((microtime(true) - $start) < 10.0) {
        $chunk = fread($upstream, 8192);
        if ($chunk !== false && $chunk !== '') {
            $response .= $chunk;
            if (str_contains($response, "\r\n\r\n")) {
                break;
            }
        }
        usleep(5000);
    }

    if (!str_contains($response, '200')) {
        fwrite($client, "HTTP/1.1 502 Bad Gateway\r\n\r\n");
        @fclose($upstream);
        @fclose($client);
        continue;
    }

    // ── 200 Connection Established → начинаем forwarding ──

    fwrite($client, "HTTP/1.1 200 Connection Established\r\n\r\n");

    // Переключаем оба потока в blocking для надёжного fread/fwrite
    stream_set_blocking($client, true);
    stream_set_blocking($upstream, true);

    // pcntl_fork: родитель — client→upstream, ребёнок — upstream→client
    if (!function_exists('pcntl_fork')) {
        // Fallback: однопоточный forwarding через stream_select
        // Менее надёжен для TLS/WebSocket, но работает для коротких запросов
        forwardSelect($client, $upstream);
        @fclose($upstream);
        @fclose($client);
        continue;
    }

    $pid = pcntl_fork();
    if ($pid === -1) {
        // fork failed — fallback на stream_select
        forwardSelect($client, $upstream);
        @fclose($upstream);
        @fclose($client);
        continue;
    }

    if ($pid === 0) {
        // ── Ребёнок: upstream → client ──
        fclose($server);
        pipeStream($upstream, $client);
        fclose($client);
        fclose($upstream);
        exit(0);
    }

    // ── Родитель: client → upstream ──
    $childPids[] = $pid;
    pipeStream($client, $upstream);
    fclose($upstream);
    fclose($client);

    // Ждём ребёнка (non-blocking)
    foreach ($childPids as $i => $cpid) {
        $res = pcntl_waitpid($cpid, $status, WNOHANG);
        if ($res > 0) {
            unset($childPids[$i]);
        }
    }
}

// ─── Functions ─────────────────────────────────────────────────────────

/**
 * Blocking pipe: читает из $source и пишет в $dest.
 * Завершается при EOF или ошибке записи.
 *
 * @param resource $source
 * @param resource $dest
 */
function pipeStream($source, $dest): void
{
    while (true) {
        $data = @fread($source, 65536);
        if ($data === '' || $data === false) {
            break;
        }
        $written = @fwrite($dest, $data);
        if ($written === false) {
            break;
        }
        fflush($dest);
    }
}

/**
 * Fallback bidirectional forwarding через stream_select.
 * Используется когда pcntl_fork недоступен.
 */
/**
 * Fallback bidirectional forwarding через stream_select.
 * Используется когда pcntl_fork недоступен.
 *
 * @param resource $client
 * @param resource $upstream
 */
function forwardSelect($client, $upstream): void
{
    $startTime = time();
    while (true) {
        if (time() - $startTime > 300) {
            break;
        }

        $read = [$client, $upstream];
        $write = null;
        $except = null;
        $changed = @stream_select($read, $write, $except, 1);
        if ($changed === false) {
            break;
        }

        if ($changed === 0) {
            continue;
        }

        foreach ($read as $socket) {
            $data = @fread($socket, 65536);
            if ($data === '' || $data === false) {
                if (feof($socket)) {
                    break 2;
                }
                continue;
            }

            $dest = ($socket === $client) ? $upstream : $client;
            @fwrite($dest, $data);
            fflush($dest);
        }
    }
}
