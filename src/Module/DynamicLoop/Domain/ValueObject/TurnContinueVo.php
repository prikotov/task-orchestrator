<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Сигнал продолжения dynamic-цикла: facilitator решил дать слово участнику.
 *
 * Discriminated union: один из двух возможных результатов turn'а.
 * Альтернатива — TurnBreakVo (сигнал прерывания/завершения).
 *
 * Инвариант: используется, когда цикл должен продолжить следующую итерацию.
 * nextRole может быть null (например, participant turn без routing).
 */
final readonly class TurnContinueVo
{
    public function __construct(
        public ?string $nextRole = null,
        public ?string $challenge = null,
    ) {
    }
}
