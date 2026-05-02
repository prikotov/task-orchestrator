<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Condition;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ConditionOperatorEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ConditionExpressionVo;

/**
 * Domain Service: вычисление условного выражения (when-expression).
 *
 * Чистая логика без I/O. Разрешает path references вида:
 *   steps.<name>.passed  → context[<name>]['passed']
 *   steps.<name>.exitCode → context[<name>]['exitCode']
 *   steps.<name>.status  → context[<name>]['status']
 *
 * Поддерживаемые операторы: == (equals), != (notEquals).
 * Type coercion: строковые 'true'/'false' → bool, числовые строки → int.
 */
final readonly class EvaluateConditionService implements EvaluateConditionServiceInterface
{
    #[Override]
    public function evaluate(ConditionExpressionVo $expression, array $context): bool
    {
        $actualValue = $this->resolveValue($expression, $context);
        $expected = $expression->getExpectedValue();

        return match ($expression->getOperator()) {
            ConditionOperatorEnum::equals => $this->isEqual($actualValue, $expected),
            ConditionOperatorEnum::notEquals => !$this->isEqual($actualValue, $expected),
        };
    }

    /**
     * Разрешает path reference в фактическое значение из контекста.
     *
     * Path format: steps.<name>.<property>
     *
     * @param array<string, array{passed?: bool, exitCode?: int, status?: string}> $context
     */
    private function resolveValue(ConditionExpressionVo $expression, array $context): string
    {
        if (!$expression->referencesStep()) {
            return '';
        }

        $stepName = $expression->getReferencedStepName();
        if ($stepName === null || !isset($context[$stepName])) {
            return '';
        }

        $property = $this->extractProperty($expression->getPath());
        $stepData = $context[$stepName];

        return match ($property) {
            'passed' => isset($stepData['passed']) ? ($stepData['passed'] ? 'true' : 'false') : '',
            'exitCode' => isset($stepData['exitCode']) ? (string) $stepData['exitCode'] : '',
            'status' => $stepData['status'] ?? '',
            default => '',
        };
    }

    /**
     * Извлекает имя свойства из path: "steps.<name>.<property>" → "property".
     */
    private function extractProperty(string $path): string
    {
        $parts = explode('.', $path);

        return $parts[2] ?? '';
    }

    /**
     * Сравнивает фактическое значение с ожидаемым с учётом type coercion.
     */
    private function isEqual(string $actual, string $expected): bool
    {
        // Нормализация: trim и lowercase для bool-значений
        $normalizedExpected = strtolower(trim($expected));
        $normalizedActual = strtolower(trim($actual));

        // Прямое строковое сравнение
        if ($normalizedActual === $normalizedExpected) {
            return true;
        }

        // Числовое сравнение (для exitCode: "0" == "0")
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return false;
    }
}
