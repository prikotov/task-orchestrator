<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Component\AgentRunner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    Dto\ProcessLivenessActiveProbeResultDto,
    Dto\ProcessLivenessInactiveProbeResultDto,
    Dto\ProcessLivenessSnapshotDto,
    Dto\ProcessLivenessUnknownProbeResultDto,
    ProcessLivenessProbeLinuxProcfsComponent,
};

#[CoversClass(ProcessLivenessProbeLinuxProcfsComponent::class)]
final class ProcessLivenessProbeLinuxProcfsComponentTest extends TestCase
{
    #[Test]
    public function probeValidParentWithEmptyChildrenBuildsComparableSnapshot(): void
    {
        // Arrange
        $filesystem = $this->filesystem($this->validFiles(
            processName: 'worker ) with (spaces',
            userTicks: 10,
            systemTicks: 5,
            readCharacters: 100,
            writtenCharacters: 20,
        ));
        $component = new ProcessLivenessProbeLinuxProcfsComponent($filesystem);

        // Act
        $first = $component->probe(42, null);
        self::assertInstanceOf(ProcessLivenessActiveProbeResultDto::class, $first);
        $second = $component->probe(42, $first->snapshot);

        // Assert
        self::assertSame([42], array_keys($first->snapshot->processes));
        self::assertSame(1_000, $first->snapshot->processes[42]->startTimeTicks);
        self::assertSame(15, $first->snapshot->processes[42]->cpuTicks);
        self::assertSame(120, $first->snapshot->processes[42]->ioCharacters);
        $this->assertCanonicalSnapshot($first->snapshot);
        self::assertInstanceOf(ProcessLivenessInactiveProbeResultDto::class, $second);
        self::assertEquals($first->snapshot->processes, $second->snapshot?->processes);
    }

    #[Test]
    public function probeDirectChildrenBuildsSortedOneLevelSnapshot(): void
    {
        // Arrange
        $files = $this->validFiles();
        $files['/proc/42/task/42/children'] = "84 21\n";
        $files += $this->processFiles(21, 'first child', 2, 3, 4, 5, 42);
        $files += $this->processFiles(84, 'second child', 6, 7, 8, 9, 42);
        $files['/proc/21/task/21/children'] = '99';
        $files += $this->processFiles(99, 'grandchild', 100, 100, 100, 100, 21);
        $filesystem = $this->filesystem($files);
        $component = new ProcessLivenessProbeLinuxProcfsComponent($filesystem);

        // Act
        $result = $component->probe(42, null);

        // Assert
        self::assertInstanceOf(ProcessLivenessActiveProbeResultDto::class, $result);
        self::assertSame([21, 42, 84], array_keys($result->snapshot?->processes ?? []));
        $this->assertCanonicalSnapshot($result->snapshot);
        self::assertNotContains('/proc/21/task/21/children', $filesystem->readPaths);
        self::assertNotContains('/proc/99/stat', $filesystem->readPaths);
    }

    #[Test]
    public function probeAggregatesDirectChildrenCreatedByEveryThread(): void
    {
        // Arrange
        $files = $this->validFiles();
        $files['/proc/42/task/77/children'] = "84\n";
        $files += $this->processFiles(84, 'worker-thread child', 6, 7, 8, 9, 42);
        $filesystem = $this->filesystem($files, ['77', '42']);
        $component = new ProcessLivenessProbeLinuxProcfsComponent($filesystem);

        // Act
        $result = $component->probe(42, null);

        // Assert
        self::assertInstanceOf(ProcessLivenessActiveProbeResultDto::class, $result);
        self::assertSame([42, 84], array_keys($result->snapshot?->processes ?? []));
        $this->assertCanonicalSnapshot($result->snapshot);
        self::assertSame(['/proc/42/task'], $filesystem->listedPaths);
        self::assertContains('/proc/42/task/42/children', $filesystem->readPaths);
        self::assertContains('/proc/42/task/77/children', $filesystem->readPaths);
    }

    /**
     * @param list<string>|null       $threadEntries
     * @param array<string, string> $threadChildren
     */
    #[Test]
    #[DataProvider('unstableThreadSampleProvider')]
    public function probeThreadEnumerationRaceOrDuplicateChildReturnsUnknown(
        ?array $threadEntries,
        array $threadChildren,
    ): void {
        // Arrange
        $files = $this->validFiles();
        foreach ($threadChildren as $threadId => $childrenContents) {
            $files[sprintf('/proc/42/task/%s/children', $threadId)] = $childrenContents;
        }

        $filesystem = $this->filesystem($files, $threadEntries);
        $component = new ProcessLivenessProbeLinuxProcfsComponent($filesystem);

        // Act
        $result = $component->probe(42, null);

        // Assert
        self::assertInstanceOf(ProcessLivenessUnknownProbeResultDto::class, $result);
    }

    /**
     * @return iterable<string, array{0: list<string>|null, 1: array<string, string>}>
     */
    public static function unstableThreadSampleProvider(): iterable
    {
        yield 'task directory disappears' => [null, []];
        yield 'leader thread disappears from listing' => [['77'], ['77' => '84']];
        yield 'duplicate thread entry' => [['42', '42'], []];
        yield 'malformed thread entry' => [['42', 'worker'], []];
        yield 'overflow thread entry' => [['42', (string) PHP_INT_MAX . '0'], []];
        yield 'worker thread disappears before children read' => [['42', '77'], []];
        yield 'worker children are malformed' => [['42', '77'], ['77' => '84 invalid']];
        yield 'same child is reported by two threads' => [
            ['42', '77'],
            ['42' => '84', '77' => '84'],
        ];
    }

    #[Test]
    public function probeTopologyAdditionAndCounterGrowthReturnActive(): void
    {
        // Arrange
        $filesystem = $this->filesystem($this->validFiles());
        $component = new ProcessLivenessProbeLinuxProcfsComponent($filesystem);
        $baseline = $this->initialSnapshot($component);

        $filesWithChild = $this->validFiles();
        $filesWithChild['/proc/42/task/42/children'] = '84';
        $filesWithChild += $this->processFiles(84, 'child', 1, 1, 1, 1, 42);
        $filesystem->files = $filesWithChild;

        // Act
        $topologyChanged = $component->probe(42, $baseline);
        self::assertInstanceOf(ProcessLivenessActiveProbeResultDto::class, $topologyChanged);
        $filesystem->files['/proc/84/stat'] = $this->stat(84, 'child', 2, 1, 42);
        $counterGrew = $component->probe(42, $topologyChanged->snapshot);

        // Assert
        self::assertInstanceOf(ProcessLivenessActiveProbeResultDto::class, $counterGrew);
    }

    #[Test]
    public function probeDirectChildRemovalReturnsActiveWithNewBaseline(): void
    {
        // Arrange
        $filesWithChild = $this->validFiles();
        $filesWithChild['/proc/42/task/42/children'] = '84';
        $filesWithChild += $this->processFiles(84, 'child', 1, 1, 1, 1, 42);
        $filesystem = $this->filesystem($filesWithChild);
        $component = new ProcessLivenessProbeLinuxProcfsComponent($filesystem);
        $baseline = $this->initialSnapshot($component);
        $filesystem->files = $this->validFiles();

        // Act
        $result = $component->probe(42, $baseline);

        // Assert
        self::assertInstanceOf(ProcessLivenessActiveProbeResultDto::class, $result);
        self::assertSame([42], array_keys($result->snapshot?->processes ?? []));
    }

    #[Test]
    public function probeUnchangedParentAndDirectChildReturnInactive(): void
    {
        // Arrange
        $files = $this->validFiles();
        $files['/proc/42/task/42/children'] = '84';
        $files += $this->processFiles(84, 'child', 1, 1, 1, 1, 42);
        $filesystem = $this->filesystem($files);
        $component = new ProcessLivenessProbeLinuxProcfsComponent($filesystem);
        $baseline = $this->initialSnapshot($component);

        // Act
        $result = $component->probe(42, $baseline);

        // Assert
        self::assertInstanceOf(ProcessLivenessInactiveProbeResultDto::class, $result);
        self::assertSame([42, 84], array_keys($result->snapshot?->processes ?? []));
    }

    #[Test]
    public function probeSamePidWithNewStartTimeReturnsActiveAsTopologyChange(): void
    {
        // Arrange
        $files = $this->validFiles();
        $files['/proc/42/task/42/children'] = '84';
        $files += $this->processFiles(84, 'old child', 10, 10, 10, 10, 42, 2_000);
        $filesystem = $this->filesystem($files);
        $component = new ProcessLivenessProbeLinuxProcfsComponent($filesystem);
        $baseline = $this->initialSnapshot($component);
        $filesystem->files = array_replace(
            $filesystem->files,
            $this->processFiles(84, 'new child', 0, 0, 0, 0, 42, 3_000),
        );

        // Act
        $result = $component->probe(42, $baseline);

        // Assert
        self::assertInstanceOf(ProcessLivenessActiveProbeResultDto::class, $result);
        self::assertSame(3_000, $result->snapshot?->processes[84]->startTimeTicks);
    }

    #[Test]
    public function probeCpuCounterRollbackForSameProcessGenerationReturnsUnknown(): void
    {
        // Arrange
        $filesystem = $this->filesystem($this->validFiles(userTicks: 10, systemTicks: 5));
        $component = new ProcessLivenessProbeLinuxProcfsComponent($filesystem);
        $baseline = $this->initialSnapshot($component);
        $filesystem->files = $this->validFiles(userTicks: 9, systemTicks: 5);

        // Act
        $result = $component->probe(42, $baseline);

        // Assert
        self::assertInstanceOf(ProcessLivenessUnknownProbeResultDto::class, $result);
    }

    #[Test]
    public function probeIoCounterRollbackForSameProcessGenerationReturnsUnknown(): void
    {
        // Arrange
        $filesystem = $this->filesystem($this->validFiles(readCharacters: 100, writtenCharacters: 20));
        $component = new ProcessLivenessProbeLinuxProcfsComponent($filesystem);
        $baseline = $this->initialSnapshot($component);
        $filesystem->files = $this->validFiles(readCharacters: 99, writtenCharacters: 20);

        // Act
        $result = $component->probe(42, $baseline);

        // Assert
        self::assertInstanceOf(ProcessLivenessUnknownProbeResultDto::class, $result);
    }

    /**
     * @param \Closure(array<string, string|null>): array<string, string|null> $mutate
     */
    #[Test]
    #[DataProvider('invalidSampleProvider')]
    public function probeIncompleteMalformedOrOverflowSampleReturnsUnknown(\Closure $mutate): void
    {
        // Arrange
        $files = $mutate($this->validFiles());
        $filesystem = $this->filesystem($files);
        $component = new ProcessLivenessProbeLinuxProcfsComponent($filesystem);

        // Act
        $result = $component->probe(42, null);

        // Assert
        self::assertInstanceOf(ProcessLivenessUnknownProbeResultDto::class, $result);
    }

    /**
     * @return iterable<string, array{0: \Closure(array<string, string|null>): array<string, string|null>}>
     */
    public static function invalidSampleProvider(): iterable
    {
        $overflow = (string) PHP_INT_MAX . '0';

        yield 'children unreadable' => [static function (array $files): array {
            $files['/proc/42/task/42/children'] = null;

            return $files;
        }];
        yield 'malformed child pid' => [static function (array $files): array {
            $files['/proc/42/task/42/children'] = '84 nope';

            return $files;
        }];
        yield 'overflow child pid' => [static function (array $files) use ($overflow): array {
            $files['/proc/42/task/42/children'] = $overflow;

            return $files;
        }];
        yield 'duplicate child pid' => [static function (array $files): array {
            $files['/proc/42/task/42/children'] = '84 84';

            return $files;
        }];
        yield 'parent repeated as child' => [static function (array $files): array {
            $files['/proc/42/task/42/children'] = '42';

            return $files;
        }];
        yield 'truncated stat' => [static function (array $files): array {
            $files['/proc/42/stat'] = '42 (worker) S 1 2';

            return $files;
        }];
        yield 'malformed stat field' => [static function (array $files): array {
            $files['/proc/42/stat'] = '42 (worker) S 1 2 3 4 5 6 bad 8 9 10 11 12';

            return $files;
        }];
        yield 'stat truncated after cpu counters' => [static function (array $files): array {
            $files['/proc/42/stat'] = '42 (worker) S 1 2 3 4 5 6 7 8 9 10 11 12';

            return $files;
        }];
        yield 'malformed trailing stat field' => [static function (array $files): array {
            $files['/proc/42/stat'] .= ' malformed';

            return $files;
        }];
        yield 'extra numeric stat field' => [static function (array $files): array {
            $files['/proc/42/stat'] .= ' 0';

            return $files;
        }];
        yield 'stat pid mismatch' => [static function (array $files): array {
            $files['/proc/42/stat'] = str_replace('42 (', '43 (', $files['/proc/42/stat'] ?? '');

            return $files;
        }];
        yield 'stat counter overflow' => [static function (array $files) use ($overflow): array {
            $files['/proc/42/stat'] = self::createStat(42, 'worker', $overflow, '0');

            return $files;
        }];
        yield 'stat counter sum overflow' => [static function (array $files): array {
            $files['/proc/42/stat'] = self::createStat(42, 'worker', (string) PHP_INT_MAX, '1');

            return $files;
        }];
        yield 'stat start time overflow' => [static function (array $files) use ($overflow): array {
            $files['/proc/42/stat'] = self::createStat(42, 'worker', '1', '1', startTimeTicks: $overflow);

            return $files;
        }];
        yield 'missing rchar' => [static function (array $files): array {
            $files['/proc/42/io'] = "wchar: 20\n";

            return $files;
        }];
        yield 'malformed wchar' => [static function (array $files): array {
            $files['/proc/42/io'] = "rchar: 10\nwchar: nope\n";

            return $files;
        }];
        yield 'io counter overflow' => [static function (array $files) use ($overflow): array {
            $files['/proc/42/io'] = "rchar: {$overflow}\nwchar: 0\n";

            return $files;
        }];
        yield 'io counter sum overflow' => [static function (array $files): array {
            $files['/proc/42/io'] = sprintf("rchar: %d\nwchar: 1\n", PHP_INT_MAX);

            return $files;
        }];
        yield 'parent stat disappears' => [static function (array $files): array {
            $files['/proc/42/stat'] = null;

            return $files;
        }];
        yield 'child disappears after enumeration' => [static function (array $files): array {
            $files['/proc/42/task/42/children'] = '84';
            $files['/proc/84/stat'] = null;
            $files['/proc/84/io'] = "rchar: 0\nwchar: 0\n";

            return $files;
        }];
        yield 'enumerated pid is no longer a direct child' => [static function (array $files): array {
            $files['/proc/42/task/42/children'] = '84';
            $files['/proc/84/stat'] = self::createStat(84, 'reused pid', '1', '1', 777);
            $files['/proc/84/io'] = "rchar: 0\nwchar: 0\n";

            return $files;
        }];
    }

    /**
     * @return array<string, string|null>
     */
    private function validFiles(
        string $processName = 'worker',
        int $userTicks = 10,
        int $systemTicks = 5,
        int $readCharacters = 100,
        int $writtenCharacters = 20,
    ): array {
        return [
            '/proc/42/task/42/children' => '',
            ...$this->processFiles(
                42,
                $processName,
                $userTicks,
                $systemTicks,
                $readCharacters,
                $writtenCharacters,
            ),
        ];
    }

    /**
     * @param array<string, string|null> $files
     * @param list<string>|null          $threadEntries
     */
    private function filesystem(array $files, ?array $threadEntries = ['42']): ProcFilesystemFake
    {
        return new ProcFilesystemFake(
            files: $files,
            directories: ['/proc/42/task' => $threadEntries],
        );
    }

    private function initialSnapshot(
        ProcessLivenessProbeLinuxProcfsComponent $component,
    ): ProcessLivenessSnapshotDto {
        $result = $component->probe(42, null);
        self::assertInstanceOf(ProcessLivenessActiveProbeResultDto::class, $result);

        return $result->snapshot;
    }

    private function assertCanonicalSnapshot(ProcessLivenessSnapshotDto $snapshot): void
    {
        $processIds = array_keys($snapshot->processes);
        self::assertNotSame([], $processIds);
        $sortedProcessIds = $processIds;
        sort($sortedProcessIds, SORT_NUMERIC);
        self::assertSame($sortedProcessIds, $processIds);
        self::assertSame($processIds, array_values(array_unique($processIds)));

        foreach ($snapshot->processes as $processId => $processSnapshot) {
            self::assertSame($processId, $processSnapshot->processId);
        }
    }

    /**
     * @return array<string, string>
     */
    private function processFiles(
        int $processId,
        string $processName,
        int $userTicks,
        int $systemTicks,
        int $readCharacters,
        int $writtenCharacters,
        int $parentProcessId = 1,
        int $startTimeTicks = 1_000,
    ): array {
        return [
            sprintf('/proc/%d/stat', $processId) => $this->stat(
                $processId,
                $processName,
                $userTicks,
                $systemTicks,
                $parentProcessId,
                $startTimeTicks,
            ),
            sprintf('/proc/%d/io', $processId) => sprintf(
                "rchar: %d\nwchar: %d\nsyscr: 0\nsyscw: 0\n",
                $readCharacters,
                $writtenCharacters,
            ),
        ];
    }

    private function stat(
        int $processId,
        string $processName,
        int $userTicks,
        int $systemTicks,
        int $parentProcessId = 1,
        int $startTimeTicks = 1_000,
    ): string {
        return self::createStat(
            $processId,
            $processName,
            (string) $userTicks,
            (string) $systemTicks,
            $parentProcessId,
            (string) $startTimeTicks,
        );
    }

    private static function createStat(
        int $processId,
        string $processName,
        string $userTicks,
        string $systemTicks,
        int $parentProcessId = 1,
        string $startTimeTicks = '1000',
    ): string {
        $fieldsAfterName = [
            'S',
            (string) $parentProcessId,
            '2',
            '3',
            '4',
            '5',
            '6',
            '7',
            '8',
            '9',
            '10',
            $userTicks,
            $systemTicks,
            ...array_fill(0, 6, '0'),
            $startTimeTicks,
            ...array_fill(0, 30, '0'),
        ];

        return sprintf('%d (%s) %s', $processId, $processName, implode(' ', $fieldsAfterName));
    }
}
