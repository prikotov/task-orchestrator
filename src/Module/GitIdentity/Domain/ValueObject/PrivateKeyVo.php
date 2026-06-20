<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject;

use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;

/**
 * Value Object PEM private key GitHub App.
 *
 * Хранит секретное содержимое ключа и предоставляет его ТОЛЬКО через
 * {@see getContent()} — для подписи JWT. Класс сознательно не реализует
 * {@see \StringValue} / {@see __toString()}: случайное преобразование в строку
 * (логирование, конкатенация, исключения) раскрыло бы секрет.
 *
 * Безопасность:
 *   - нет {@see __toString()};
 *   - {@see __debugInfo()} возвращает redacted значение;
 *   - отпечаток {@see fingerprint()} раскрывает лишь короткий префикс SHA-256.
 *
 * Содержимое ключа никогда не должно попадать в сообщения исключений.
 */
final readonly class PrivateKeyVo
{
    public function __construct(private string $content)
    {
        if ($content === '') {
            throw new InvalidConfigurationException('GitHub App private key must not be empty.');
        }
        // Грубая проверка «PEM-like»: наличие BEGIN/PRIVATE KEY маркеров.
        if (!str_contains($content, '-----BEGIN') || !str_contains($content, 'PRIVATE KEY-----')) {
            throw new InvalidConfigurationException(
                'GitHub App private key does not look like a PEM-encoded key.',
            );
        }
    }

    /**
     * Возвращает PEM-содержимое ключа строго для подписи JWT.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Возвращает короткий отпечаток ключа (SHA-256, первые 16 hex-символов)
     * для безопасной диагностики/логирования без раскрытия секрета.
     */
    public function fingerprint(): string
    {
        return 'sha256:' . substr(hash('sha256', $this->content), 0, 16);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['content' => '[redacted]'];
    }
}
