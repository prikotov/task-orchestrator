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
        private ?string $nextRole = null,
        private ?string $challenge = null,
    ) {
    }

    public function getNextRole(): ?string
    {
        return $this->nextRole;
    }

    public function getChallenge(): ?string
    {
        return $this->challenge;
    }
}
