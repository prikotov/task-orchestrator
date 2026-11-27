<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * VO результата одного хода фасилитатора (Domain-аналог FacilitatorTurnResultDto).
 */
final readonly class FacilitatorTurnResultVo
{
    public function __construct(
        private DynamicRoundResultVo $roundResult,
        private bool $done,
        private ?string $nextRole,
        private ?string $synthesis,
        private ?string $challenge = null,
        private string $userPrompt = '',
    ) {
    }

    public function getRoundResult(): DynamicRoundResultVo
    {
        return $this->roundResult;
    }

    /**
     * Завершил ли фасилитатор обсуждение (synthesis получен).
     */
    public function isDone(): bool
    {
        return $this->done;
    }

    public function getNextRole(): ?string
    {
        return $this->nextRole;
    }

    public function getSynthesis(): ?string
    {
        return $this->synthesis;
    }

    public function getChallenge(): ?string
    {
        return $this->challenge;
    }

    public function getUserPrompt(): string
    {
        return $this->userPrompt;
    }
}
