<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopJournal;

#[CoversClass(DynamicLoopJournal::class)]
final class DynamicLoopJournalTest extends TestCase
{
    #[Test]
    public function constructorDefaultsAreEmpty(): void
    {
        $journal = new DynamicLoopJournal();

        self::assertSame('', $journal->getDiscussionHistory());
        self::assertSame('', $journal->getFacilitatorJournal());
        self::assertSame('', $journal->getFacilitatorSummary());
    }

    #[Test]
    public function constructorAcceptsInitialValues(): void
    {
        $journal = new DynamicLoopJournal('Initial discussion', 'Initial journal');

        self::assertSame('Initial discussion', $journal->getDiscussionHistory());
        self::assertSame('Initial journal', $journal->getFacilitatorJournal());
    }

    #[Test]
    public function appendDiscussionHistoryConcatenatesWithoutSeparators(): void
    {
        $journal = new DynamicLoopJournal();

        $journal->appendDiscussionHistory('Part A');
        $journal->appendDiscussionHistory(' Part B');

        self::assertSame('Part A Part B', $journal->getDiscussionHistory());
    }

    #[Test]
    public function appendFacilitatorJournalConcatenatesWithoutSeparators(): void
    {
        $journal = new DynamicLoopJournal('', 'Base');

        $journal->appendFacilitatorJournal(' +append');

        self::assertSame('Base +append', $journal->getFacilitatorJournal());
    }

    #[Test]
    public function appendFacilitatorSummaryConcatenatesWithoutSeparators(): void
    {
        $journal = new DynamicLoopJournal();

        $journal->appendFacilitatorSummary('Round 1: x');
        $journal->appendFacilitatorSummary("\nRound 2: y");

        self::assertSame("Round 1: x\nRound 2: y", $journal->getFacilitatorSummary());
    }

    #[Test]
    public function setDiscussionHistoryFullyReplacesValue(): void
    {
        $journal = new DynamicLoopJournal('Original');

        $journal->setDiscussionHistory('Replaced');

        self::assertSame('Replaced', $journal->getDiscussionHistory());
    }

    #[Test]
    public function setFacilitatorJournalFullyReplacesValue(): void
    {
        $journal = new DynamicLoopJournal('', 'Original');

        $journal->setFacilitatorJournal('Replaced');

        self::assertSame('Replaced', $journal->getFacilitatorJournal());
    }
}
