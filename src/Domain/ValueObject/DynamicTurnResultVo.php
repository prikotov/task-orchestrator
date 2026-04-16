<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\ValueObject;

/**
 * Результат обработки одного turn'а (facilitator/participant) в dynamic-цикле.
 *
 * Используется для передачи решения из методов executeFacilitatorTurn /
 * executeParticipantTurn в основной цикл. Содержит либо данные для
 * продолжения (nextRole, challenge), либо сигнал завершения (synthesis,
 * shouldBreak), либо бюджетный результат.
 */
final readonly class DynamicTurnResultVo
{
    public function __construct(
        public bool $shouldBreak = false,
        public ?string $interruptionReason = null,
        public ?string $synthesis = null,
        public ?string $nextRole = null,
        public ?string $challenge = null,
        public ?DynamicBudgetCheckVo $budgetResult = null,
    ) {
    }
}
