<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\PharCache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\PharCache\CacheRootCleanupReport;

/**
 * Модульные тесты отчёта очистки кеш-корней PHAR ({@see CacheRootCleanupReport}):
 * признак сбоев и безопасная сводка (только счётчики, без путей).
 */
#[CoversClass(CacheRootCleanupReport::class)]
final class CacheRootCleanupReportTest extends TestCase
{
    #[Test]
    public function cleanRunHasNoFailures(): void
    {
        $report = new CacheRootCleanupReport(
            performed: true,
            examinedEntries: 3,
            removedRoots: 1,
            unknownPreserved: 2,
        );

        self::assertTrue($report->performed);
        self::assertNull($report->skipReason);
        self::assertFalse($report->hasFailures());
    }

    #[Test]
    public function failedRemovalIsReported(): void
    {
        $report = new CacheRootCleanupReport(performed: true, removedRoots: 1, failures: 1);

        self::assertTrue($report->hasFailures());
    }

    #[Test]
    public function skippedReportIsNotPerformedAndHasReason(): void
    {
        $report = CacheRootCleanupReport::skipped(CacheRootCleanupReport::SKIP_REASON_POSIX_UNAVAILABLE);

        self::assertFalse($report->performed);
        self::assertSame(CacheRootCleanupReport::SKIP_REASON_POSIX_UNAVAILABLE, $report->skipReason);
        self::assertFalse($report->hasFailures());
    }

    #[Test]
    public function safeSummaryContainsOnlyCounters(): void
    {
        $report = new CacheRootCleanupReport(
            performed: true,
            examinedEntries: 5,
            removedRoots: 2,
            removedSymlinks: 1,
            activePreserved: 1,
            unknownPreserved: 1,
            foreignPreserved: 0,
            failures: 1,
        );

        $summary = $report->toSafeSummary();

        self::assertSame('рассмотрено: 5, удалено: 3, сохранено: 2, ошибок: 1', $summary);
    }
}
