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
     * Сложность задачи по умолчанию: уровень C3 (единый контракт с навыком run-subagent).
     * Сессии, созданные до появления поля complexity в session.json, считаются C3.
     */
    public const int DEFAULT_COMPLEXITY = 3;

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
        private int $complexity = self::DEFAULT_COMPLEXITY,
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

    /**
     * Сложность задачи уровнем 0–5 (C0–C5), зафиксированная при запуске сессии.
     */
    public function getComplexity(): int
    {
        return $this->complexity;
    }
}
