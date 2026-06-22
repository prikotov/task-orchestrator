<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Infrastructure\Component\GitHub;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub\Exception\GitHubHttpException;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub\GitHubHttpComponent;

/**
 * Unit-тесты {@see GitHubHttpComponent} на Symfony MockHttpClient (без сети).
 *
 * Покрывает контракт компонента по конвенции External Service: проброс заголовка
 * Authorization (bearer), структурированное логирование запроса (без секретов),
 * ручная классификация HTTP-статусов (404 vs 400..403 vs 5xx) и обёртка
 * транспортных/декодирующих исключений в {@see GitHubHttpException}.
 */
#[CoversClass(GitHubHttpComponent::class)]
final class GitHubHttpComponentTest extends TestCase
{
    private const string URL = 'https://api.github.test/repos/octocat/Hello-World/installation';

    private const string BEARER = 'header.payload.signature';

    private SpyLogger $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->logger = new SpyLogger();
    }

    #[Test]
    public function requestSendsBearerAuthorizationHeader(): void
    {
        $captured = $this->captureOptions(new MockResponse('{"id": 42}', ['http_code' => 200]));

        $component = new GitHubHttpComponent(new MockHttpClient($captured->client), $this->logger);
        $component->request('GET', self::URL, self::BEARER, null);

        self::assertSame('Bearer ' . self::BEARER, $this->extractHeader($captured->options, 'Authorization'));
        // GET без тела не должен добавлять Content-Type.
        self::assertNull($this->extractHeader($captured->options, 'Content-Type'));
    }

    #[Test]
    public function postWithBodyAddsJsonContentType(): void
    {
        $captured = $this->captureOptions(new MockResponse('{"token": "ghs_x"}', ['http_code' => 200]));

        $component = new GitHubHttpComponent(new MockHttpClient($captured->client), $this->logger);
        $component->request('POST', self::URL, self::BEARER, '{"repository_names":["octocat/Hello-World"]}');

        self::assertSame('Bearer ' . self::BEARER, $this->extractHeader($captured->options, 'Authorization'));
        self::assertSame('application/json', $this->extractHeader($captured->options, 'Content-Type'));
    }

    #[Test]
    public function requestReturnsDecodedJsonArray(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"id": 424242, "node_id": "abc"}', ['http_code' => 200]),
        ]);

        $component = new GitHubHttpComponent($client, $this->logger);
        $data = $component->request('GET', self::URL, self::BEARER, null);

        self::assertSame(['id' => 424242, 'node_id' => 'abc'], $data);
    }

    #[Test]
    public function requestLogsInfoWithoutBearer(): void
    {
        $client = new MockHttpClient([new MockResponse('{"id": 1}', ['http_code' => 200])]);

        $component = new GitHubHttpComponent($client, $this->logger);
        $component->request('GET', self::URL, self::BEARER, null);

        $info = $this->logger->find('info', 'GitHub API request');
        self::assertNotNull($info, 'info-запись о запросе должна быть залогирована.');
        self::assertSame(['method' => 'GET', 'url' => self::URL], $info['context']);
        // Bearer не должен утекать ни в контекст лога, ни в сообщение.
        self::assertStringNotContainsString(self::BEARER, $info['message']);
    }

    #[Test]
    public function notFoundThrowsWithHttpStatusAndWithoutErrorLog(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"message": "Not Found"}', ['http_code' => 404]),
        ]);

        $component = new GitHubHttpComponent($client, $this->logger);

        try {
            $component->request('GET', self::URL, self::BEARER, null);
            self::fail('Ожидалось GitHubHttpException для 404.');
        } catch (GitHubHttpException $e) {
            self::assertTrue($e->isNotFound(), 'isNotFound() должен быть true для 404.');
            self::assertSame(404, $e->getHttpStatus());
        }

        // 404 — обработанный бизнес-кейс, error-лог не пишется.
        self::assertNull($this->logger->find('error', 'GitHub API error'));
    }

    #[Test]
    public function clientErrorLogsAndThrows(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"message": "Bad request"}', ['http_code' => 400]),
        ]);

        $component = new GitHubHttpComponent($client, $this->logger);

        try {
            $component->request('GET', self::URL, self::BEARER, null);
            self::fail('Ожидалось GitHubHttpException для 400.');
        } catch (GitHubHttpException $e) {
            self::assertSame(400, $e->getHttpStatus());
            self::assertFalse($e->isNotFound());
        }

        $error = $this->logger->find('error', 'GitHub API error');
        self::assertNotNull($error, '400..403 должен логироваться как error.');
        self::assertSame(['status' => 400, 'url' => self::URL], $error['context']);
    }

    #[Test]
    public function serverErrorThrowsForHttpStatus(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"message": "Server Error"}', ['http_code' => 500]),
        ]);

        $component = new GitHubHttpComponent($client, $this->logger);

        $this->expectException(GitHubHttpException::class);
        $this->expectExceptionMessage('HTTP 500');

        $component->request('GET', self::URL, self::BEARER, null);
    }

    #[Test]
    public function invalidJsonResponseThrows(): void
    {
        $client = new MockHttpClient([
            new MockResponse('not-a-json', ['http_code' => 200]),
        ]);

        $component = new GitHubHttpComponent($client, $this->logger);

        $this->expectException(GitHubHttpException::class);
        $this->expectExceptionMessage('invalid JSON');

        $component->request('GET', self::URL, self::BEARER, null);
    }

    #[Test]
    public function transportExceptionIsWrapped(): void
    {
        // Callback имитирует сетевой сбой — выбрасывает TransportException из HttpClient.
        $client = new MockHttpClient(static function (): MockResponse {
            throw new TransportException('Connection refused');
        });

        $component = new GitHubHttpComponent($client, $this->logger);

        try {
            $component->request('GET', self::URL, self::BEARER, null);
            self::fail('Ожидалось GitHubHttpException для сетевой ошибки.');
        } catch (GitHubHttpException $e) {
            self::assertStringContainsString('network error', $e->getMessage());
            self::assertInstanceOf(TransportException::class, $e->getPrevious());
        }
    }

    /**
     * Регистрирует callback, фиксирующий опции запроса, и возвращает их вместе с клиентом-фабрикой.
     *
     * @return \stdClass{client: callable, options: array<string, mixed>}
     */
    private function captureOptions(MockResponse $response): object
    {
        $bag = new \stdClass();
        $bag->options = [];
        $bag->client = static function (string $method, string $url, array $options) use ($response, $bag): MockResponse {
            $bag->options = $options + ['method' => $method, 'url' => $url];

            return $response;
        };

        return $bag;
    }

    /**
     * Извлекает значение HTTP-заголовка из опций запроса (поддерживает ассоциативный
     * и строковый "Name: value" форматы, используемые Symfony HttpClient).
     *
     * @param array<string, mixed> $options
     */
    private function extractHeader(array $options, string $name): ?string
    {
        $headers = $options['headers'] ?? [];
        $lower = strtolower($name);

        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                if (str_starts_with(strtolower((string) $value), $lower . ':')) {
                    return trim(substr((string) $value, strlen($name) + 1));
                }
            } elseif (strtolower((string) $key) === $lower) {
                return $value;
            }
        }

        return null;
    }
}

/**
 * PSR-3 логгер-шпион: записывает все вызовы для последующих утверждений в тестах.
 */
final class SpyLogger extends AbstractLogger implements LoggerInterface
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param mixed[] $context
     */
    #[\Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return array{level: string, message: string, context: array<string, mixed>}|null
     */
    public function find(string $level, string $message): ?array
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                return $record;
            }
        }

        return null;
    }
}
