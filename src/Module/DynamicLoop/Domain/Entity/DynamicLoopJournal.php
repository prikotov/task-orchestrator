<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity;

/**
 * Owned mutable-компонент агрегата DynamicLoopExecution: journal state.
 *
 * @internal Owned by DynamicLoopExecution. Не самостоятельная сущность и не aggregate root.
 *
 * Инкапсулирует текстовые журналы:
 * - discussionHistory — история обсуждения
 * - facilitatorJournal — журнал фасилитатора
 * - facilitatorSummary — краткая сводка фасилитатора
 *
 * Append-операции — чистая конкатенация без разделителей.
 */
final class DynamicLoopJournal
{
    private string $discussionHistory;
    private string $facilitatorJournal;
    private string $facilitatorSummary = '';

    public function __construct(
        string $discussionHistory = '',
        string $facilitatorJournal = '',
    ) {
        $this->discussionHistory = $discussionHistory;
        $this->facilitatorJournal = $facilitatorJournal;
    }

    public function getDiscussionHistory(): string
    {
        return $this->discussionHistory;
    }

    public function setDiscussionHistory(string $history): void
    {
        $this->discussionHistory = $history;
    }

    public function appendDiscussionHistory(string $entry): void
    {
        $this->discussionHistory .= $entry;
    }

    public function getFacilitatorJournal(): string
    {
        return $this->facilitatorJournal;
    }

    public function setFacilitatorJournal(string $journal): void
    {
        $this->facilitatorJournal = $journal;
    }

    public function appendFacilitatorJournal(string $entry): void
    {
        $this->facilitatorJournal .= $entry;
    }

    public function getFacilitatorSummary(): string
    {
        return $this->facilitatorSummary;
    }

    public function appendFacilitatorSummary(string $entry): void
    {
        $this->facilitatorSummary .= $entry;
    }
}
