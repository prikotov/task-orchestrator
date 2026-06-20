<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service;

use JsonException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;

/**
 * Внутренний HTTP-транспорт для GitHub API.
 *
 * Общая переиспользуемая функциональность двух GitHub-сервисов
 * ({@see GitHubResolveInstallationIdService} и {@see GitHubRequestInstallationTokenService}):
 * выполнение запроса через file_get_contents + stream_context, разбор статуса
 * ответа и JSON. Трейт stateless и не содержит свойств.
 *
 * Безопасность: JWT/token никогда не попадают в сообщения исключений —
 * формируются только технические детали (метод, sanitized URL, HTTP-статус).
 */
trait GitHubHttpTransportTrait
{
    /**
     * @param string $method HTTP-метод (GET/POST).
     * @param string $url Полный URL запроса.
     * @param string $bearer Bearer-значение JWT (для Authorization, не логируется).
     * @param GitIdentityConfigVo $config Конфигурация (headers, timeout).
     * @param string|null $body Тело запроса для POST (JSON) или null.
     *
     * @return string Тело ответа при успехе.
     *
     * @throws GitHubApiException при сетевой ошибке, не-2xx ответе.
     */
    private function githubRequest(
        string $method,
        string $url,
        string $bearer,
        GitIdentityConfigVo $config,
        ?string $body,
    ): string {
        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: ' . $config->getGitHubApiVersion(),
            'User-Agent: ' . $config->getUserAgent(),
            'Authorization: Bearer ' . $bearer,
        ];
        if ($method === 'POST' && $body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'content' => $body ?? '',
                'timeout' => $config->getRequestTimeoutSeconds(),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        // $http_response_header заполняется HTTP-обёрткой PHP; инициализируем явно.
        /** @var list<string> $http_response_header */
        $http_response_header = [];

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new GitHubApiException(
                sprintf(
                    'GitHub API request failed: network error for %s %s',
                    $method,
                    $url,
                ),
            );
        }

        $status = $this->parseStatus($http_response_header);
        if ($status >= 400) {
            throw GitHubApiException::forHttpStatus(
                $status,
                sprintf(
                    'GitHub API error: HTTP %d for %s %s',
                    $status,
                    $method,
                    $url,
                ),
            );
        }

        return $response;
    }

    /**
     * @param string $body Тело ответа.
     *
     * @return array<string, mixed>
     *
     * @throws GitHubApiException при невалидном JSON.
     */
    private function githubDecodeJson(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new GitHubApiException('GitHub API: invalid JSON response.');
        }
        if (!is_array($decoded)) {
            throw new GitHubApiException('GitHub API: unexpected non-object response.');
        }

        return $decoded;
    }

    /**
     * @param list<string> $responseHeaders
     */
    private function parseStatus(array $responseHeaders): int
    {
        foreach ($responseHeaders as $line) {
            if (preg_match('#^HTTP/[\d.]+ (\d{3})#', $line, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}
