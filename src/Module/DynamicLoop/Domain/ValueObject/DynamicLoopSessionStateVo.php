<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Value Object состояния прерванной сессии dynamic-цикла для resume.
 *
 * Копия ChainDefinition\ChainSessionStateVo, без зависимости от ChainDefinition.Domain.
 */
final readonly class DynamicLoopSessionStateVo
{
    /**
     * @param list<string> $participants
     */
    public function __construct(
        private string $topic,
        private string $facilitator,
        private array $participants,
        private int $maxRounds,
        private int $completedRounds,
        private string $discussionHistory,
        private string $facilitatorJournal,
    ) {
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getFacilitator(): string
    {
        return $this->facilitator;
    }

    /**
     * @return list<string>
     */
    public function getParticipants(): array
    {
        return $this->participants;
    }

    public function getMaxRounds(): int
    {
        return $this->maxRounds;
    }

    public function getCompletedRounds(): int
    {
        return $this->completedRounds;
    }

    public function getDiscussionHistory(): string
    {
        return $this->discussionHistory;
    }

    public function getFacilitatorJournal(): string
    {
        return $this->facilitatorJournal;
    }
}
