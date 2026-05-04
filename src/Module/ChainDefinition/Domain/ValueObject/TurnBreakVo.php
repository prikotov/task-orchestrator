<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

/**
 * Сигнал прерывания/завершения dynamic-цикла.
 *
 * Discriminated union: один из двух возможных результатов turn'а.
 * Альтернатива — TurnContinueVo (сигнал продолжения).
 *
 * Варианты использования:
 * - budget_exceeded: бюджет превышен (budgetResult заполнен)
 * - agent_error / timeout: ошибка агента
 * - synthesis: фасилитатор завершил обсуждение
 */
final readonly class TurnBreakVo
{
    public function __construct(
        public ?string $interruptionReason = null,
        public ?string $synthesis = null,
        public ?DynamicBudgetCheckVo $budgetResult = null,
    ) {
    }
}
