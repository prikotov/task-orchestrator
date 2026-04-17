<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * Value Object состояния прерванной сессии для resume.
 */
final readonly class ChainSessionStateVo
{
    public function __construct(
        private string $topic,
        private string $facilitator,
        /** @var list<string> */
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
