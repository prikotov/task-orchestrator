<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Результат проверки бюджета dynamic-цикла.
 *
 * Возвращается CheckDynamicBudgetServiceInterface::checkAfterTurn().
 * Может сигнализировать либо о превышении бюджета (isBreakNeeded=true),
 * либо о предупреждении 80% (isWarning80Triggered=true).
 */
final readonly class DynamicBudgetCheckVo
{
    public function __construct(
        private bool $shouldBreak = false,
        private bool $budgetExceeded = false,
        private float $budgetLimit = 0.0,
        private ?string $budgetExceededRole = null,
        private string $warningMessage = '',
        private bool $warning80Triggered = false,
    ) {
    }

    /**
     * Нужно ли прервать выполнение цикла.
     */
    public function isBreakNeeded(): bool
    {
        return $this->shouldBreak;
    }

    public function isBudgetExceeded(): bool
    {
        return $this->budgetExceeded;
    }

    public function getBudgetLimit(): float
    {
        return $this->budgetLimit;
    }

    public function getBudgetExceededRole(): ?string
    {
        return $this->budgetExceededRole;
    }

    public function getWarningMessage(): string
    {
        return $this->warningMessage;
    }

    /**
     * Сработало ли предупреждение о достижении 80% бюджета.
     */
    public function isWarning80Triggered(): bool
    {
        return $this->warning80Triggered;
    }
}
