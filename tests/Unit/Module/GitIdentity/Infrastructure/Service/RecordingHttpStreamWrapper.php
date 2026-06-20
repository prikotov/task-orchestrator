<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Infrastructure\Service;

/**
 * Тестовый PHP stream wrapper (шпион) для перехвата HTTP-запросов GitHub API,
 * которые {@see \TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\GitHubHttpTransportTrait}
 * выполняет через `file_get_contents` + `stream_context_create`.
 *
 * Регистрируется под произвольной схемой (например, `gitmock://`) через
 * `stream_wrapper_register()`. Фиксирует запрошенный URL в {@see $lastUrl}
 * и возвращает предзаписанное тело ответа {@see $responseBody} без обращения
 * к сети, что делает тест детерминированным.
 *
 * Не использует контекст stream_context (опции http/ssl игнорируются custom
 * wrapper'ом) — это безопасно, т.к. wrapper предназначен только для unit-тестов.
 */
class RecordingHttpStreamWrapper
{
    /**
     * Контекст stream_context_create (обязательное публичное свойство для PHP wrapper protocol).
     *
     * @var resource|null
     */
    public $context;

    public static ?string $lastUrl = null;

    public static string $responseBody = '';

    private int $offset = 0;

    /**
     * @param-out string|null $openedPath
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$lastUrl = $path;
        $this->offset = 0;

        return true;
    }

    public function stream_read(int $count): string|false
    {
        $length = strlen(self::$responseBody);
        if ($this->offset >= $length) {
            return '';
        }
        $chunk = substr(self::$responseBody, $this->offset, $count);
        $this->offset += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->offset >= strlen(self::$responseBody);
    }

    /**
     * @return array<string, int>|false
     */
    public function stream_stat(): array|false
    {
        return ['size' => strlen(self::$responseBody)];
    }

    public function stream_close(): void
    {
        // no-op
    }

    public static function reset(?string $responseBody = null): void
    {
        self::$lastUrl = null;
        self::$responseBody = $responseBody ?? '';
    }
}
