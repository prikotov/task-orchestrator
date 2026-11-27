<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

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
        private ?string $interruptionReason = null,
        private ?string $synthesis = null,
        private ?DynamicBudgetCheckVo $budgetResult = null,
    ) {
    }

    public function getInterruptionReason(): ?string
    {
        return $this->interruptionReason;
    }

    public function getSynthesis(): ?string
    {
        return $this->synthesis;
    }

    public function getBudgetResult(): ?DynamicBudgetCheckVo
    {
        return $this->budgetResult;
    }
}
