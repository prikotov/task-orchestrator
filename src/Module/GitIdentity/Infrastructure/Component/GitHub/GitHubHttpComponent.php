<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub;

use Override;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub\Exception\GitHubHttpException;

/**
 * Реализация HTTP-компонента GitHub API на Symfony HttpClient.
 *
 * Следует конвенции External Service: использует преднастроенный scoped-клиент
 * {@see http_client.git_identity} (таймауты и статические заголовки Accept,
 * X-GitHub-Api-Version, User-Agent), логирует факт запроса и ошибки, оборачивает
 * все транспортные исключения в Infrastructure-исключение {@see GitHubHttpException}.
 *
 * Статусы ответа обрабатываются вручную до декодирования, чтобы различать:
 *  - >= 404 — выбрасывается без error-лога (404 = business-кейс «installation
 *    удалена», детектируемый через {@see GitHubHttpException::isNotFound()});
 *  - 400..403 — логируется как error и выбрасывается.
 */
final readonly class GitHubHttpComponent implements GitHubHttpComponentInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    #[Override]
    public function request(string $method, string $url, string $bearer, ?string $body = null): array
    {
        // Логируем только технические детали: bearer/token в логи не попадают.
        $this->logger->info('GitHub API request', [
            'method' => $method,
            'url' => $url,
        ]);

        try {
            $requestOptions = [
                'headers' => $this->buildHeaders($bearer, $body),
            ];
            // body передаётся только для POST: на GET-запрос body=null порождает
            // заголовок Content-Length: 0, что часть серверов (включая GitHub при
            // определённых условиях) обрабатывает некорректно.
            if ($body !== null) {
                $requestOptions['body'] = $body;
            }

            $response = $this->httpClient->request($method, $url, $requestOptions);

            $status = $response->getStatusCode();

            // 404+ — обработанный бизнес-кейс (см. isNotFound()), без error-лога.
            if ($status >= 404) {
                throw GitHubHttpException::forHttpStatus(
                    $status,
                    sprintf('GitHub API error: HTTP %d for %s %s', $status, $method, $url),
                );
            }

            if ($status >= 400) {
                $this->logger->error('GitHub API error', [
                    'status' => $status,
                    'url' => $url,
                ]);

                throw GitHubHttpException::forHttpStatus(
                    $status,
                    sprintf('GitHub API error: HTTP %d for %s %s', $status, $method, $url),
                );
            }

            return $response->toArray();
        } catch (DecodingExceptionInterface $e) {
            throw new GitHubHttpException('GitHub API: invalid JSON response.', 0, $e);
        } catch (TransportExceptionInterface $e) {
            throw new GitHubHttpException(
                sprintf('GitHub API request failed: network error for %s %s', $method, $url),
                0,
                $e,
            );
        }
    }

    /**
     * Формирует динамические заголовки запроса.
     *
     * Статические заголовки (Accept, X-GitHub-Api-Version, User-Agent) заданы
     * scoped-клиентом; здесь добавляется только меняющийся от запроса к запросу
     * Authorization и Content-Type для тел.
     *
     * @return array<string, string>
     */
    private function buildHeaders(string $bearer, ?string $body): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $bearer,
        ];

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }
}
