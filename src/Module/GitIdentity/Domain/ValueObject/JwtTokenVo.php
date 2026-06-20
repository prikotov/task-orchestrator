<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject;

use DateTimeImmutable;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;

/**
 * Value Object подписанного App JWT (RS256) для GitHub App authentication.
 *
 * Хранит секретное bearer-значение токена. Класс сознательно не реализует
 * {@see __toString()}: случайное преобразование в строку раскрыло бы секрет.
 * Безопасность: {@see __debugInfo()} возвращает redacted значение.
 *
 * Чистый детерминированный VO: конструктор не читает системные часы. Проверка
 * истечения срока — pure-метод {@see isExpiredAt()} (время передаётся
 * вызывающей стороной, обычно Application через ClockServiceInterface), что
 * делает VO предсказуемым и тестируемым с фиксированным временем.
 */
final readonly class JwtTokenVo
{
    public function __construct(private string $value, private DateTimeImmutable $expiresAt)
    {
        if ($value === '') {
            throw new InvalidConfigurationException('JWT token must not be empty.');
        }
    }

    /**
     * Возвращает bearer-значение JWT для GitHub App auth.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }
}
