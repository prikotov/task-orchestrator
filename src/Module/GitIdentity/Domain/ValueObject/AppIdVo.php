<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject;

use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;

/**
 * Value Object идентификатора GitHub App.
 *
 * Неотрицательный (положительный) целый идентификатор App.
 * Неизменяемый, валидация в конструкторе; при нарушении инварианта
 * выбрасывается {@see InvalidConfigurationException} (контракт модуля:
 * все публичные ошибки — потомки {@see \TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitIdentityException}).
 */
final readonly class AppIdVo
{
    public function __construct(private int $value)
    {
        if ($value <= 0) {
            throw new InvalidConfigurationException(
                sprintf('GitHub App ID must be a positive integer, got %d.', $value),
            );
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
