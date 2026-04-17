<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * Результат проверки бюджета dynamic-цикла.
 *
 * Возвращается CheckDynamicBudgetServiceInterface::checkAfterTurn().
 * Может сигнализировать либо о превышении бюджета (shouldBreak=true),
 * либо о предупреждении 80% (warning80Triggered=true).
 */
final readonly class DynamicBudgetCheckVo
{
    public function __construct(
        public bool $shouldBreak = false,
        public bool $budgetExceeded = false,
        public float $budgetLimit = 0.0,
        public ?string $budgetExceededRole = null,
        public string $warningMessage = '',
        public bool $warning80Triggered = false,
    ) {
    }
}
