<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Condition;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ConditionExpressionVo;

/**
 * Domain Service: вычисление условного выражения (when-expression).
 *
 * Принимает ConditionExpressionVo + контекст результатов предыдущих шагов → bool.
 * Контекст — map вида: {stepName: {passed: bool, exitCode: int, status: string}}.
 */
interface EvaluateConditionServiceInterface
{
    /**
     * Вычисляет условное выражение на основе контекста выполнения предыдущих шагов.
     *
     * @param ConditionExpressionVo $expression условное выражение (when-expression)
     * @param array<string, array{passed?: bool, exitCode?: int, status?: string}> $context
     *     контекст: map stepName → {passed, exitCode, status}
     */
    public function evaluate(ConditionExpressionVo $expression, array $context): bool;
}
