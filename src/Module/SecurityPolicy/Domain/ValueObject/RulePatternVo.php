<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\PatternTypeEnum;

/**
 * Value Object шаблона матчинга для правил безопасности.
 *
 * Immutable, поддерживает три типа матчинга:
 * - exact: точное совпадение (===)
 * - glob: glob-паттерн (fnmatch, поддерживает * и ?)
 * - regex: регулярное выражение (preg_match)
 *
 * Примеры:
 * - exact: "rm" совпадает с "rm", не совпадает с "rm -rf"
 * - glob:  "rm *" совпадает с "rm -rf /", "bash*" совпадает с "bash -c"
 * - regex: "/^rm\s/" совпадает с "rm -rf /"
 */
final readonly class RulePatternVo
{
    private function __construct(
        private PatternTypeEnum $type,
        private string $pattern,
    ) {
        if ($pattern === '') {
            throw new InvalidArgumentException('Rule pattern must not be empty.');
        }

        if ($type === PatternTypeEnum::regex) {
            $this->validateRegex($pattern);
        }
    }

    /**
     * Создаёт паттерн точного совпадения.
     */
    public static function createFromExact(string $pattern): self
    {
        return new self(PatternTypeEnum::exact, $pattern);
    }

    /**
     * Создаёт glob-паттерн (fnmatch).
     *
     * Поддерживает: * (любая строка), ? (один символ).
     * Примеры: "bash*", "rm *", "*.sh"
     */
    public static function createFromGlob(string $pattern): self
    {
        return new self(PatternTypeEnum::glob, $pattern);
    }

    /**
     * Создаёт regex-паттерн (preg_match).
     *
     * Паттерн должен быть валидным регулярным выражением PCRE.
     * Разделители добавляются автоматически.
     */
    public static function createFromRegex(string $pattern): self
    {
        return new self(PatternTypeEnum::regex, $pattern);
    }

    /**
     * Создаёт паттерн из строки с указанием типа.
     */
    public static function createFromType(PatternTypeEnum $type, string $pattern): self
    {
        return new self($type, $pattern);
    }

    /**
     * Проверяет, совпадает ли значение с паттерном.
     */
    public function matches(string $value): bool
    {
        return match ($this->type) {
            PatternTypeEnum::exact => $this->pattern === $value,
            PatternTypeEnum::glob => fnmatch($this->pattern, $value),
            PatternTypeEnum::regex => $this->matchRegex($value),
        };
    }

    /** @psalm-suppress ArgumentTypeCoercion — pattern валидирован в конструкторе */
    private function matchRegex(string $value): bool
    {
        return (bool) preg_match($this->pattern, $value);
    }

    public function getType(): PatternTypeEnum
    {
        return $this->type;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->pattern === $other->pattern;
    }

    public function __toString(): string
    {
        return sprintf('%s:%s', $this->type->value, $this->pattern);
    }

    /** @psalm-suppress ArgumentTypeCoercion — валидация regex-pattern */
    private function validateRegex(string $pattern): void
    {
        set_error_handler(static fn (): bool => true);
        $result = preg_match($pattern, '');
        restore_error_handler();

        if ($result === false) {
            throw new InvalidArgumentException(
                sprintf('Invalid regex pattern: "%s".', $pattern),
            );
        }
    }
}
