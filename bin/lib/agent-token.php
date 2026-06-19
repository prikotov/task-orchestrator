<?php

declare(strict_types=1);

/**
 * Тестируемые функции для bin/agent-token.
 *
 * Не являются частью PSR-4 автозагрузки — подключаются напрямую через require_once.
 * Вынесены из bin/agent-token для возможности unit-тестирования без сети.
 *
 * Функции в глобальном namespace (без namespace), чтобы bin-скрипт и тесты могли
 * вызывать их без сложных манипуляций с автозагрузкой.
 *
 * @see bin/agent-token
 */

/**
 * Base64url-кодирование (URL-safe, без паддинга).
 */
function agent_token_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64url-декодирование.
 */
function agent_token_base64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder > 0) {
        $data .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode(strtr($data, '-_', '+/'), strict: true);
    if ($decoded === false) {
        throw new \RuntimeException('Invalid base64url data');
    }

    return $decoded;
}

/**
 * Собирает JWT-токен (RS256).
 *
 * header = {alg: RS256, typ: JWT}
 * payload = {iat, exp = iat + 9min, iss = appId}
 *
 * @param int $appId GitHub App ID
 * @param string $pemContent Содержимое PEM-файла (private key)
 *
 * @return string Сформированный JWT (header.payload.signature)
 *
 * @throws \RuntimeException если подпись не удалась
 */
function agent_token_build_jwt(int $appId, string $pemContent): string
{
    $privateKey = openssl_pkey_get_private($pemContent);
    if ($privateKey === false) {
        throw new \RuntimeException(
            'Failed to load private key: ' . agent_token_cleanup_openssl_error()
        );
    }

    $now = time();
    $payload = [
        'iat' => $now - 60, // GitHub рекомендует запас на clock skew (отклоняет JWT с iat «в будущем»)

        'exp' => ($now - 60) + (9 * 60),
        'iss' => $appId,
    ];

    $header = agent_token_base64url_encode(
        json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)
    );
    $payloadEncoded = agent_token_base64url_encode(
        json_encode($payload, JSON_THROW_ON_ERROR)
    );

    $dataToSign = $header . '.' . $payloadEncoded;

    $signature = '';
    $success = openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    // OpenSSLAsymmetricKey освобождается автоматически (PHP 8.0+)

    if ($success === false) {
        throw new \RuntimeException(
            'Failed to sign JWT: ' . agent_token_cleanup_openssl_error()
        );
    }

    return $dataToSign . '.' . agent_token_base64url_encode($signature);
}

/**
 * Парсит аргумент <owner>/<repo>.
 *
 * @return array{owner: string, repo: string}
 *
 * @throws \InvalidArgumentException если формат некорректный
 */
function agent_token_parse_repo_argument(string $arg): array
{
    $parts = explode('/', $arg, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        throw new \InvalidArgumentException(
            "Invalid repository format: expected <owner>/<repo>."
        );
    }

    return ['owner' => $parts[0], 'repo' => $parts[1]];
}

/**
 * Обрезает тело ответа для безопасного вывода в сообщении об ошибке.
 */
function agent_token_truncate_for_error(string $body, int $maxLength = 200): string
{
    $truncated = mb_substr($body, 0, $maxLength);
    if (mb_strlen($body) > $maxLength) {
        $truncated .= '...';
    }

    return $truncated;
}

/**
 * Вырезает конфиденциальные данные из строки (PEM-блоки, base64-фрагменты).
 *
 * Чистая функция без side-эффектов — пригодна для прямого unit-тестирования.
 *
 * Правила redaction:
 *   1. Полные PEM-блоки (-----BEGIN ... PRIVATE KEY----- ... -----END ... PRIVATE KEY-----).
 *   2. Незакрытые PEM-заголовки (без парного END).
 *   3. Длинные base64-фрагменты (>=64 символов).
 *
 * @see agent_token_cleanup_openssl_error() — использует эту функцию
 */
function agent_token_redact_message(string $msg): string
{
    // 1. Полные PEM-блоки BEGIN...END
    $msg = preg_replace(
        '/-----BEGIN[^-]*PRIVATE KEY-----.*?-----END[^-]*PRIVATE KEY-----/s',
        '[REDACTED]',
        $msg,
    ) ?? $msg;

    // 2. Незакрытые PEM-заголовки
    $msg = preg_replace(
        '/-----BEGIN[\s\-]+[^\-]*(?:PRIVATE|RSA) KEY-----?/',
        '[REDACTED]',
        $msg,
    ) ?? $msg;

    // 3. Длинные base64-фрагменты ключей (>=64 символов)
    $msg = preg_replace(
        '/[A-Za-z0-9+\/]{64,}/',
        '[REDACTED-B64]',
        $msg,
    ) ?? $msg;

    return $msg;
}

/**
 * Убирает конфиденциальные данные из сообщения об ошибке OpenSSL.
 *
 * OpenSSL иногда включает содержимое ключа или PEM в сообщения.
 * Defence-in-depth: redaction + жёсткая обрезка до 120 символов.
 * Цель: пользователь видит ТОЛЬКО технический код ошибки (error:0909006C:...),
 * никогда — содержимое ключа.
 */
function agent_token_cleanup_openssl_error(): string
{
    $msg = '';
    while ($err = openssl_error_string()) {
        $msg .= ($msg !== '' ? '; ' : '') . $err;
    }

    $msg = agent_token_redact_message($msg);

    // Жёсткая обрезка (файлрейм defence-in-depth)
    if (mb_strlen($msg) > 120) {
        $msg = mb_substr($msg, 0, 120) . '...';
    }

    return $msg !== '' ? $msg : 'unknown OpenSSL error';
}

/**
 * Загружает конфигурацию: PEM-путь, App ID.
 *
 * Источники (в порядке приоритета):
 * - PEM: env AGENT_PRIVATE_KEY_PATH, иначе ~/.config/prikotov-agent/private-key.pem
 * - App ID: env AGENT_APP_ID, иначе ~/.config/prikotov-agent/app-id
 *
 * @return array{pemPath: string, pemContent: string, appId: int}
 *
 * @throws \RuntimeException если конфиг не найден или PEM недоступен
 */
function agent_token_load_configuration(): array
{
    $pemPath = getenv('AGENT_PRIVATE_KEY_PATH');
    if ($pemPath === false || $pemPath === '') {
        $pemPath = getenv('HOME');
        if ($pemPath === false) {
            throw new \RuntimeException('HOME environment variable is not set');
        }
        $pemPath = $pemPath . '/.config/prikotov-agent/private-key.pem';
    }

    if (!file_exists($pemPath)) {
        throw new \RuntimeException(
            'PEM private key not found. Set AGENT_PRIVATE_KEY_PATH or '
            . 'place key at ~/.config/prikotov-agent/private-key.pem'
        );
    }

    $perms = fileperms($pemPath);
    if ($perms !== false && ($perms & 0o077) !== 0) {
        throw new \RuntimeException(
            'PEM file has insecure permissions. Expected 0600. '
            . 'Run: chmod 600 ' . $pemPath
        );
    }

    $pemContent = file_get_contents($pemPath);
    if ($pemContent === false) {
        throw new \RuntimeException('Failed to read PEM file.');
    }

    $appIdStr = getenv('AGENT_APP_ID');
    if ($appIdStr !== false && $appIdStr !== '') {
        $appId = (int) $appIdStr;
    } else {
        $home = getenv('HOME');
        if ($home === false) {
            throw new \RuntimeException('HOME environment variable is not set');
        }
        $appIdFile = $home . '/.config/prikotov-agent/app-id';
        if (!file_exists($appIdFile)) {
            throw new \RuntimeException(
                'App ID not found. Set AGENT_APP_ID or '
                . 'create ~/.config/prikotov-agent/app-id'
            );
        }
        $appIdStr = trim((string) file_get_contents($appIdFile));
        $appId = (int) $appIdStr;
    }

    if ($appId <= 0) {
        throw new \RuntimeException('Invalid App ID: must be a positive integer.');
    }

    return ['pemPath' => $pemPath, 'pemContent' => $pemContent, 'appId' => $appId];
}

/**
 * Выполняет HTTP-запрос к GitHub API через ext-openssl + stream_context.
 *
 * @param string $method HTTP-метод (GET / POST)
 * @param string $url Полный URL
 * @param string $jwt Bearer JWT-токен для авторизации
 * @param array{body?: string} $options Дополнительные опции (body для POST)
 *
 * @return array{status: int, body: string}
 *
 * @throws \RuntimeException при ошибках сети или HTTP 4xx/5xx
 */
function agent_token_github_api_request(
    string $method,
    string $url,
    string $jwt,
    array $options = [],
): array {
    $headers = [
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: prikotov-agent-token/1.0',
    ];

    // JWT передаётся в заголовке, но в diagnostic-сообщениях не фигурирует
    $headers[] = "Authorization: Bearer {$jwt}";

    if ($method === 'POST' && isset($options['body'])) {
        $headers[] = 'Content-Type: application/json';
    }

    $context = stream_context_create(
        [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'content' => $options['body'] ?? '',
                'timeout' => 15,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]
    );

    // $http_response_header — «магическая» переменная, заполняемая HTTP-обёрткой PHP.
    // Инициализируем явно, чтобы Psalm не ругался на неопределённую переменную.
    $http_response_header = [];

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new \RuntimeException(
            "GitHub API request failed: network error for {$method} {$url}"
        );
    }

    $status = 200;
    foreach ($http_response_header as $headerLine) {
        if (preg_match('#^HTTP/[\d.]+ (\d+)#', $headerLine, $matches)) {
            $status = (int) $matches[1];
        }
    }

    if ($status >= 400) {
        throw new \RuntimeException(
            "GitHub API error: HTTP {$status} — "
            . agent_token_truncate_for_error($response)
        );
    }

    return ['status' => $status, 'body' => $response];
}

/**
 * Возвращает путь к файлу кеша токена для данной installation_id.
 *
 * @param int $installationId GitHub installation ID
 * @param string|null $cacheDir Пользовательский каталог кеша (для тестов).
 *                              По умолчанию — var/cache/agent-token/ относительно project root.
 */
function agent_token_cache_path(int $installationId, ?string $cacheDir = null): string
{
    $cacheDir = $cacheDir ?? dirname(__DIR__, 2) . '/var/cache/agent-token';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0700, recursive: true);
    }

    return $cacheDir . '/' . $installationId . '.json';
}

/**
 * Пытается прочитать кешированный токен.
 *
 * TTL = expires_at минус 60 секунд запаса.
 *
 * @param int $installationId GitHub installation ID
 * @param string|null $cacheDir Пользовательский каталог кеша (для тестов).
 *
 * @return array{token: string, expires_at: string, installation_id: int}|null
 */
function agent_token_read_cache(int $installationId, ?string $cacheDir = null): ?array
{
    $path = agent_token_cache_path($installationId, $cacheDir);
    if (!file_exists($path)) {
        return null;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        return null;
    }

    try {
        /** @var array{token?: string, expires_at?: string} $data */
        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        if (!isset($data['token'], $data['expires_at'])) {
            return null;
        }

        $expiresAt = strtotime($data['expires_at']);
        if ($expiresAt === false || $expiresAt <= time() + 60) {
            return null;
        }

        return [
            'token' => $data['token'],
            'expires_at' => $data['expires_at'],
            'installation_id' => $installationId,
        ];
    } catch (\JsonException) {
        // Повреждённый/частичный кеш-файл → cache-miss → перевыпуск токена
        return null;
    }
}

/**
 * Записывает токен в кеш.
 *
 * @param int $installationId GitHub installation ID
 * @param array{token: string, expires_at: string} $tokenData
 * @param string|null $cacheDir Пользовательский каталог кеша (для тестов).
 */
function agent_token_write_cache(int $installationId, array $tokenData, ?string $cacheDir = null): void
{
    $path = agent_token_cache_path($installationId, $cacheDir);
    $data = array_merge($tokenData, ['installation_id' => $installationId]);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($path, 0600); // Файл кеша с токеном не должен быть world-readable
}

/**
 * Возвращает путь к файлу кеша installation_id для пары owner/repo.
 *
 * @param string $owner Имя владельца репозитория
 * @param string $repo Имя репозитория
 * @param string|null $cacheDir Пользовательский каталог кеша (для тестов).
 */
function agent_token_installation_id_cache_path(string $owner, string $repo, ?string $cacheDir = null): string
{
    $cacheDir = $cacheDir ?? dirname(__DIR__, 2) . '/var/cache/agent-token';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0700, recursive: true);
    }

    return $cacheDir . '/' . $owner . '_' . $repo . '.installation';
}

/**
 * Читает кешированный installation_id для пары owner/repo.
 *
 * @return int|null installation_id или null, если кеш отсутствует/повреждён
 */
function agent_token_read_installation_id_cache(string $owner, string $repo, ?string $cacheDir = null): ?int
{
    $path = agent_token_installation_id_cache_path($owner, $repo, $cacheDir);
    if (!file_exists($path)) {
        return null;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        return null;
    }

    try {
        $id = (int) trim($content);
        return $id > 0 ? $id : null;
    } catch (\Throwable) {
        return null;
    }
}

/**
 * Записывает installation_id в кеш для пары owner/repo.
 */
function agent_token_write_installation_id_cache(string $owner, string $repo, int $installationId, ?string $cacheDir = null): void
{
    $path = agent_token_installation_id_cache_path($owner, $repo, $cacheDir);
    file_put_contents($path, (string) $installationId);
    chmod($path, 0600);
}

/**
 * Удаляет кешированный installation_id для пары owner/repo.
 *
 * Используется при инвалидации (App удалён/переустановлен → id изменился).
 */
function agent_token_invalidate_installation_id_cache(string $owner, string $repo, ?string $cacheDir = null): void
{
    $path = agent_token_installation_id_cache_path($owner, $repo, $cacheDir);
    if (file_exists($path)) {
        @unlink($path);
    }
}

/**
 * Получает installation_id для репозитория через GitHub API.
 *
 * @throws \RuntimeException если API вернул ошибку или installation не найдена
 */
function agent_token_get_installation_id(
    string $owner,
    string $repo,
    string $jwt,
): int {
    $url = "https://api.github.com/repos/{$owner}/{$repo}/installation";
    $response = agent_token_github_api_request('GET', $url, $jwt);

    try {
        $data = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        throw new \RuntimeException(
            'GitHub API: invalid JSON response for installation lookup.'
        );
    }

    if (!isset($data['id']) || !is_int($data['id'])) {
        throw new \RuntimeException(
            'GitHub API: unexpected response — installation ID not found.'
        );
    }

    return $data['id'];
}

/**
 * Получает installation access token от GitHub App.
 *
 * @return array{token: string, expires_at: string}
 *
 * @throws \RuntimeException если API вернул ошибку
 */
function agent_token_get_access_token(int $installationId, string $jwt): array
{
    $url = "https://api.github.com/app/installations/{$installationId}/access/tokens";
    $response = agent_token_github_api_request('POST', $url, $jwt);

    try {
        $data = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        throw new \RuntimeException(
            'GitHub API: invalid JSON response for access token request.'
        );
    }

    if (!isset($data['token']) || !isset($data['expires_at'])) {
        throw new \RuntimeException(
            'GitHub API: unexpected response — token or expires_at not found.'
        );
    }

    return ['token' => $data['token'], 'expires_at' => $data['expires_at']];
}

/**
 * Основной use-case: получить installation token (с кешированием).
 *
 * Порядок:
 *   1. load_config → build_jwt
 *   2. Прочитать installation_id из кеша; если есть — проверить кеш токена.
 *      Если валидный токен есть — вернуть без сети.
 *   3. Если installation_id в кеше, но токен протух — запросить новый токен
 *      по известному installation_id (без лишнего запроса installation_id).
 *      Фоллбэк: при HTTP 404 инвалидировать кеш installation_id и запросить заново.
 *   4. Иначе получить installation_id (сеть) → получить access token → закешировать.
 *
 * @param string $owner Имя владельца репозитория
 * @param string $repo Имя репозитория
 * @param string|null $cacheDir Пользовательский каталог кеша (для тестов).
 *
 * @return array{token: string, expires_at: string, installation_id: int}
 */
function agent_token_obtain(string $owner, string $repo, ?string $cacheDir = null): array
{
    $config = agent_token_load_configuration();
    $jwt = agent_token_build_jwt($config['appId'], $config['pemContent']);

    // Пытаемся достать installation_id из кеша (без сети)
    $installationId = agent_token_read_installation_id_cache($owner, $repo, $cacheDir);
    if ($installationId !== null) {
        // Проверяем кеш токена
        $cached = agent_token_read_cache($installationId, $cacheDir);
        if ($cached !== null) {
            return $cached;
        }

        // installation_id есть в кеше, но токен протух — запрашиваем новый токен
        // по известному installation_id (избегаем лишнего сетевого запроса).
        try {
            $tokenData = agent_token_get_access_token($installationId, $jwt);
        } catch (\RuntimeException $e) {
            // App удалён/переустановлен → installation_id изменился → инвалидация
            if (str_contains($e->getMessage(), 'HTTP 404')) {
                agent_token_invalidate_installation_id_cache($owner, $repo, $cacheDir);
                $installationId = agent_token_get_installation_id($owner, $repo, $jwt);
                agent_token_write_installation_id_cache($owner, $repo, $installationId, $cacheDir);
                $tokenData = agent_token_get_access_token($installationId, $jwt);
            } else {
                throw $e;
            }
        }

        agent_token_write_cache($installationId, $tokenData, $cacheDir);

        return [
            'token' => $tokenData['token'],
            'expires_at' => $tokenData['expires_at'],
            'installation_id' => $installationId,
        ];
    }

    // Кеш miss — идём в сеть
    $installationId = agent_token_get_installation_id($owner, $repo, $jwt);
    agent_token_write_installation_id_cache($owner, $repo, $installationId, $cacheDir);

    // Проверяем кеш токена ещё раз (мог появиться от параллельного процесса)
    $cached = agent_token_read_cache($installationId, $cacheDir);
    if ($cached !== null) {
        return $cached;
    }

    $tokenData = agent_token_get_access_token($installationId, $jwt);
    agent_token_write_cache($installationId, $tokenData, $cacheDir);

    return [
        'token' => $tokenData['token'],
        'expires_at' => $tokenData['expires_at'],
        'installation_id' => $installationId,
    ];
}
