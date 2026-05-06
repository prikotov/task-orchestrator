<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ConditionOperatorEnum;

/**
 * Value Object условного выражения (when-expression) для шага цепочки.
 *
 * Парсит и хранит декларативное условие выполнения шага в формате:
 *   <path> <operator> <value>
 *
 * Примеры:
 *   steps.tests.passed == true
 *   steps.lint.exitCode != 0
 *   result.status == success
 */
final readonly class ConditionExpressionVo
{
    private function __construct(
        private string $rawExpression,
        private string $path,
        private ConditionOperatorEnum $operator,
        private string $expectedValue,
    ) {
    }

    /**
     * Создаёт VO из сырого строкового выражения.
     *
     * Формат: "<path> <operator> <value>"
     * Поддерживаемые операторы: ==, !=
     *
     * @throws InvalidArgumentException если выражение невалидно
     */
    public static function createFromExpression(string $expression): self
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new InvalidArgumentException('Condition expression must not be empty.');
        }

        if (!preg_match('/^(.+?)\s*(==|!=)\s*(.+)$/s', $expression, $matches)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid condition expression: "%s". Expected format: <path> <operator> <value> (operators: ==, !=).',
                $expression,
            ));
        }

        $path = trim($matches[1]);
        $operator = ConditionOperatorEnum::from($matches[2]);
        $expectedValue = trim($matches[3]);

        if ($path === '') {
            throw new InvalidArgumentException(sprintf(
                'Condition path must not be empty in expression: "%s".',
                $expression,
            ));
        }

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\\.[a-zA-Z_][a-zA-Z0-9_]*)+$/', $path) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid condition path: "%s". Expected format: segments.separated.by.dots (alphanumeric, underscores).',
                $path,
            ));
        }

        if ($expectedValue === '') {
            throw new InvalidArgumentException(sprintf(
                'Condition expected value must not be empty in expression: "%s".',
                $expression,
            ));
        }

        return new self(
            rawExpression: $expression,
            path: $path,
            operator: $operator,
            expectedValue: $expectedValue,
        );
    }

    /**
     * Создаёт VO из компонентов (для использования в маппере).
     */
    public static function createFromComponents(
        string $rawExpression,
        string $path,
        ConditionOperatorEnum $operator,
        string $expectedValue,
    ): self {
        return new self(
            rawExpression: $rawExpression,
            path: $path,
            operator: $operator,
            expectedValue: $expectedValue,
        );
    }

    public function getRawExpression(): string
    {
        return $this->rawExpression;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getOperator(): ConditionOperatorEnum
    {
        return $this->operator;
    }

    public function getExpectedValue(): string
    {
        return $this->expectedValue;
    }

    public function referencesStep(): bool
    {
        return str_starts_with($this->path, 'steps.');
    }

    public function getReferencedStepName(): ?string
    {
        if (!$this->referencesStep()) {
            return null;
        }

        $parts = explode('.', $this->path);

        return $parts[1] ?? null;
    }

    public function equals(self $other): bool
    {
        return $this->rawExpression === $other->rawExpression;
    }

}
