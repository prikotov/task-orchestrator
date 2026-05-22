<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex;

use InvalidArgumentException;
use RuntimeException;

/**
 * PHP HTTPS→HTTP прокси-мост для CodexAgentRunner.
 *
 * Запускает локальный HTTP-прокси на 127.0.0.1:<random_port>,
 * который принимает CONNECT-запросы и пересылает их через TLS
 * на upstream HTTPS-прокси.
 *
 * Проблема: codex CLI (reqwest 0.12.28) не поддерживает схему https:// для прокси.
 * Решение: мост слушает как обычный HTTP-прокси (http://), а сам подключается
 * к upstream через TLS, отправляя CONNECT с Proxy-Authorization.
 *
 * Bidirectional forwarding реализован через pcntl_fork() (blocking pipes),
 * fallback на stream_select() если pcntl недоступен.
 *
 * Жизненный цикл:
 * 1. CodexAgentRunner обнаруживает https:// в CODEX_HTTP_PROXY
 * 2. Создаёт HttpsProxyBridge, вызывает start()
 * 3. start() запускает отдельный PHP-процесс (proc_open) со скриптом Resources/bridge.php
 * 4. Credentials передаются через environment variables (не через cmdline — безопасность)
 * 5. Мост пишет назначенный порт в stdout — родитель читает его
 * 6. CodexAgentRunner подставляет http://127.0.0.1:<port> в env codex-процесса
 * 7. После завершения codex — вызывается stop() (SIGTERM дочернему процессу)
 *
 * @see https://github.com/seanmonstar/reqwest/issues/26 — reqwest не поддерживает HTTPS-прокси
 */
final class HttpsProxyBridge
{
    private const string BRIDGE_HOST = '127.0.0.1';

    /**
     * @var resource|false|null Дескриптор процесса proc_open
     * @phpstan-ignore property.unusedType
     */
    private $process = null;

    /** @var string Локальный URL моста (http://127.0.0.1:<port>) */
    private string $localProxyUrl = '';

    /** @var string upstream host */
    private readonly string $upstreamHost;

    /** @var int upstream port */
    private readonly int $upstreamPort;

    /** @var string upstream username (может быть пустой) */
    private readonly string $upstreamUser;

    /** @var string upstream password (может быть пустой) */
    private readonly string $upstreamPass;

    /** @var int Timeout соединения с upstream (секунды) */
    private readonly int $connectTimeout;

    /**
     * @param string $upstreamProxyUrl URL upstream HTTPS-прокси (https://user:pass@host:port)
     * @param int $connectTimeout Timeout соединения с upstream в секундах (default: 15)
     */
    public function __construct(
        string $upstreamProxyUrl,
        int $connectTimeout = 15,
    ) {
        $parsed = self::parseUpstreamUrl($upstreamProxyUrl);
        if ($parsed === null) {
            throw new InvalidArgumentException(
                sprintf('Invalid upstream HTTPS proxy URL: %s', $upstreamProxyUrl),
            );
        }

        $this->upstreamHost = $parsed['host'];
        $this->upstreamPort = $parsed['port'];
        $this->upstreamUser = $parsed['user'];
        $this->upstreamPass = $parsed['pass'];
        $this->connectTimeout = $connectTimeout;
    }

    /**
     * Деструктор: гарантирует остановку orphan-процесса.
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Запускает локальный HTTP-прокси-мост.
     *
     * Создаёт дочерний PHP-процесс со скриптом Resources/bridge.php.
     * Credentials передаются через environment variables (безопасно — не видны в /proc/<pid>/cmdline).
     * Мост слушает на 127.0.0.1:<random_port> и пересылает
     * CONNECT-запросы через TLS на upstream.
     *
     * @return string Локальный URL моста (http://127.0.0.1:<port>)
     *
     * @throws RuntimeException если не удалось запустить мост или прочитать порт
     */
    public function start(): string
    {
        if ($this->isRunning()) {
            return $this->localProxyUrl;
        }

        $command = $this->buildBridgeCommand();
        $env = $this->buildBridgeEnv();

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin — не используется
            1 => ['pipe', 'w'],  // stdout — мост пишет порт
            2 => ['pipe', 'w'],  // stderr — лог ошибок
        ];

        $process = proc_open($command, $descriptors, $pipes, null, $env);

        if ($process === false) {
            throw new RuntimeException('Failed to start HTTPS proxy bridge process.');
        }

        $this->process = $process;

        // Закрываем stdin — мост не читает из него
        fclose($pipes[0]);

        // Читаем порт из stdout моста (формат: "PORT:<number>\n")
        $portLine = $this->readPortFromStdout($pipes[1], $pipes[2]);

        // Закрываем pipes — мост работает автономно
        fclose($pipes[1]);
        fclose($pipes[2]);

        if ($portLine === null) {
            $this->killProcess();
            throw new RuntimeException(
                'HTTPS proxy bridge failed to start: could not read port from stdout.',
            );
        }

        $this->localProxyUrl = sprintf('http://%s:%d', self::BRIDGE_HOST, $portLine);

        return $this->localProxyUrl;
    }

    /**
     * Останавливает мост (SIGTERM дочернему процессу).
     *
     * Idempotent: безопасен при повторном вызове.
     */
    public function stop(): void
    {
        if (!$this->isRunning()) {
            return;
        }

        $this->killProcess();
        $this->localProxyUrl = '';
    }

    /**
     * Проверяет, запущен ли мост.
     */
    public function isRunning(): bool
    {
        if ($this->process === null || !is_resource($this->process)) {
            return false;
        }

        $status = proc_get_status($this->process);

        return $status['running'] ?? false; // @phpstan-ignore nullCoalesce.offset
    }

    /**
     * Возвращает локальный URL моста или пустую строку, если мост не запущен.
     */
    public function getLocalProxyUrl(): string
    {
        return $this->localProxyUrl;
    }

    /**
     * Парсит URL upstream HTTPS-прокси.
     *
     * Ожидает формат: https://[user:pass@]host:port
     * Возвращает null для не-HTTPS URL или невалидных URL.
     * Percent-encoded user/pass декодируются через urldecode().
     *
     * @param string $url URL прокси
     *
     * @return array{host: non-empty-string, port: int, user: string, pass: string}|null
     */
    public static function parseUpstreamUrl(string $url): ?array
    {
        if ($url === '') {
            return null;
        }

        $parsed = parse_url($url);

        if ($parsed === false) {
            return null;
        }

        $scheme = $parsed['scheme'] ?? '';
        if ($scheme !== 'https') {
            return null;
        }

        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) && $parsed['port'] > 0 ? $parsed['port'] : 0;

        if ($host === '' || $port === 0) {
            return null;
        }

        return [
            'host' => $host,
            'port' => $port,
            'user' => urldecode($parsed['user'] ?? ''),
            'pass' => urldecode($parsed['pass'] ?? ''),
        ];
    }

    /**
     * Строит команду для запуска bridge.php.
     *
     * @return list<string> массив аргументов для proc_open
     */
    private function buildBridgeCommand(): array
    {
        $bridgeScript = __DIR__ . '/Resources/bridge.php';

        return [PHP_BINARY, $bridgeScript];
    }

    /**
     * Строит массив environment variables для дочернего процесса моста.
     *
     * Credentials передаются через env (не через cmdline) —
     * это предотвращает утечку в /proc/<pid>/cmdline и ps aux.
     *
     * @return array<string, string> переменные окружения для proc_open
     */
    private function buildBridgeEnv(): array
    {
        // Proxy-Authorization: Basic <credentials>
        $authHeader = '';
        if ($this->upstreamUser !== '' || $this->upstreamPass !== '') {
            $credentials = base64_encode($this->upstreamUser . ':' . $this->upstreamPass);
            $authHeader = 'Proxy-Authorization: Basic ' . $credentials;
        }

        $env = [
            'BRIDGE_UPSTREAM_HOST' => $this->upstreamHost,
            'BRIDGE_UPSTREAM_PORT' => (string) $this->upstreamPort,
            'BRIDGE_AUTH_HEADER' => $authHeader,
            'BRIDGE_CONNECT_TIMEOUT' => (string) $this->connectTimeout,
            'BRIDGE_HOST' => self::BRIDGE_HOST,
        ];

        // Pass through TLS configuration (for testing with self-signed certs)
        foreach (['BRIDGE_TLS_VERIFY', 'BRIDGE_CA_FILE'] as $var) {
            $value = getenv($var);
            if ($value !== false) {
                $env[$var] = $value;
            }
        }

        return $env;
    }

    /**
     * Читает строку PORT:<number> из stdout дочернего процесса.
     *
     * Таймаут: 5 секунд. Если мост не стартанул за это время — ошибка.
     *
     * @param resource $stdout pipe stdout
     * @param resource $stderr pipe stderr
     *
     * @return int|null Номер порта или null при ошибке
     */
    private function readPortFromStdout($stdout, $stderr): ?int
    {
        $timeout = 5.0;
        $start = microtime(true);

        stream_set_blocking($stdout, false);
        stream_set_blocking($stderr, false);

        $buffer = '';

        while ((microtime(true) - $start) < $timeout) {
            $chunk = fread($stdout, 1024);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;

                // Ищем маркер PORT:<number>\n
                if (preg_match('/^PORT:(\d+)\n/', $buffer, $matches)) {
                    return (int) $matches[1];
                }
            }

            // Проверяем, не упал ли процесс
            if (!$this->isRunning()) {
                return null;
            }

            usleep(10000); // 10ms
        }

        return null;
    }

    /**
     * Убивает дочерний процесс и закрывает дескриптор.
     */
    private function killProcess(): void
    {
        if ($this->process !== null && is_resource($this->process)) {
            proc_terminate($this->process, SIGTERM);

            // Ждём завершения до 2 секунд
            $start = microtime(true);
            while ($this->isRunning() && (microtime(true) - $start) < 2.0) {
                usleep(50000); // 50ms
            }

            // Если не завершился — SIGKILL
            if ($this->isRunning()) {
                proc_terminate($this->process, SIGKILL);
            }

            proc_close($this->process);
        }

        $this->process = null;
    }
}
