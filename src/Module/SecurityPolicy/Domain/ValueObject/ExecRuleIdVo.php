<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object идентификатора правила безопасности.
 *
 * Immutable, содержит строковый идентификатор ExecRule.
 */
final readonly class ExecRuleIdVo
{
    private function __construct(
        private string $value,
    ) {
        if ($value === '') {
            throw new InvalidArgumentException('ExecRule ID must not be empty.');
        }
    }

    public static function createFromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
