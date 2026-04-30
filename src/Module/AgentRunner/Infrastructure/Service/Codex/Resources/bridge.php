<?php

/**
 * HTTPS→HTTP прокси-мост для CodexAgentRunner.
 *
 * Запускается как отдельный PHP-процесс через proc_open из HttpsProxyBridge.
 * Слушает на 127.0.0.1:0 (ОС назначает свободный порт), принимает HTTP CONNECT-запросы
 * и пересылает их через TLS на upstream HTTPS-прокси.
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

while (true) {
    $client = @stream_socket_accept($server, 60);
    if ($client === false) {
        continue;
    }

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

    $connectRequest = "CONNECT {$targetHost}:{$targetPort} HTTP/1.1\r\n"
        . "Host: {$targetHost}:{$targetPort}\r\n";
    if ($authHeader !== false && $authHeader !== '') {
        $connectRequest .= $authHeader . "\r\n";
    }
    $connectRequest .= "\r\n";

    fwrite($upstream, $connectRequest);

    $response = '';
    $start = microtime(true);
    stream_set_blocking($upstream, false);
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

    fwrite($client, "HTTP/1.1 200 Connection Established\r\n\r\n");

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
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            continue;
        }

        foreach ($read as $socket) {
            $data = @fread($socket, 65536);
            if ($data === false || $data === '') {
                break 2;
            }

            $dest = ($socket === $client) ? $upstream : $client;
            @fwrite($dest, $data);
        }

        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }

    @fclose($upstream);
    @fclose($client);
}
