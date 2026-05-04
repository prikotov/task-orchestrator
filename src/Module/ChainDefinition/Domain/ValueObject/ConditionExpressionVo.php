<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ConditionOperatorEnum;

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
 *
 * Expression — это данные (data), не логика.
 * Evaluation logic будет в ConditionalExecutionStrategy (следующая задача).
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

        if (!self::isValidPath($path)) {
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
     * Возвращает исходное строковое выражение.
     */
    public function getRawExpression(): string
    {
        return $this->rawExpression;
    }

    /**
     * Возвращает левую часть выражения — path reference (например, "steps.tests.passed").
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Возвращает оператор сравнения.
     */
    public function getOperator(): ConditionOperatorEnum
    {
        return $this->operator;
    }

    /**
     * Возвращает правую часть выражения — ожидаемое значение (строковое представление).
     *
     * Приведение типов (true/false → bool, 0 → int) выполняется при evaluation.
     */
    public function getExpectedValue(): string
    {
        return $this->expectedValue;
    }

    /**
     * Проверяет, ссылается ли condition на результат именованного шага.
     *
     * Формат: steps.<name>.<property>
     */
    public function referencesStep(): bool
    {
        return str_starts_with($this->path, 'steps.');
    }

    /**
     * Извлекает имя шага из path reference.
     *
     * Для "steps.tests.passed" вернёт "tests".
     * Для path, не начинающегося с "steps.", вернёт null.
     */
    public function getReferencedStepName(): ?string
    {
        if (!$this->referencesStep()) {
            return null;
        }

        $parts = explode('.', $this->path);
        // parts[0] = 'steps', parts[1] = name, parts[2+] = property
        return $parts[1] ?? null;
    }

    /**
     * Сравнивает два ConditionExpressionVo на равенство по значениям.
     */
    public function equals(self $other): bool
    {
        return $this->rawExpression === $other->rawExpression;
    }

    /**
     * Валидирует path reference: сегменты из букв, цифр и подчёркиваний, разделённые точками.
     */
    private static function isValidPath(string $path): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)+$/', $path) === 1;
    }
}
