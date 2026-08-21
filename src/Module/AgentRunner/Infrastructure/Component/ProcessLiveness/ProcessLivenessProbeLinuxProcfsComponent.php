<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness;

use Override;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessActiveProbeResultDto;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessInactiveProbeResultDto;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessPidSnapshotDto;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessSnapshotDto;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessUnknownProbeResultDto;

/**
 * Linux liveness-проба через procfs без внешних команд и расширения pcntl.
 *
 * Читает основной PID и его непосредственных детей из children-файлов всех
 * task TID. Любая неполная, повреждённая или переполненная выборка возвращает
 * UNKNOWN целиком.
 */
final readonly class ProcessLivenessProbeLinuxProcfsComponent implements ProcessLivenessProbeComponentInterface
{
    public function __construct(
        private ProcFilesystemComponentInterface $filesystem,
        private string $procRoot = '/proc',
    ) {
    }

    #[Override]
    public function probe(
        int $processId,
        ?ProcessLivenessSnapshotDto $previousSnapshot,
    ): ProcessLivenessActiveProbeResultDto
        | ProcessLivenessInactiveProbeResultDto
        | ProcessLivenessUnknownProbeResultDto {
        $childProcessIds = $this->collectChildProcessIds($processId);
        if ($childProcessIds === null) {
            return $this->unknownResult();
        }

        $processIds = [$processId, ...$childProcessIds];
        sort($processIds, SORT_NUMERIC);

        $processSnapshots = [];
        foreach ($processIds as $snapshotProcessId) {
            $processSnapshot = $this->readProcessSnapshot(
                processId: $snapshotProcessId,
                expectedParentProcessId: $snapshotProcessId === $processId ? null : $processId,
            );
            if ($processSnapshot === null) {
                return $this->unknownResult();
            }

            $processSnapshots[$snapshotProcessId] = $processSnapshot;
        }

        $snapshot = new ProcessLivenessSnapshotDto($processSnapshots);

        return $this->resolveResult($snapshot, $previousSnapshot);
    }

    private function readProcessSnapshot(
        int $processId,
        ?int $expectedParentProcessId,
    ): ?ProcessLivenessPidSnapshotDto {
        $statContents = $this->filesystem->read(sprintf('%s/%d/stat', $this->procRoot, $processId));
        $ioContents = $this->filesystem->read(sprintf('%s/%d/io', $this->procRoot, $processId));
        if ($statContents === null || $ioContents === null) {
            return null;
        }

        $stat = $this->parseStat($statContents, $processId, $expectedParentProcessId);
        $ioCharacters = $this->parseIoCharacters($ioContents);
        if ($stat === null || $ioCharacters === null) {
            return null;
        }

        return new ProcessLivenessPidSnapshotDto(
            processId: $processId,
            startTimeTicks: $stat['startTimeTicks'],
            cpuTicks: $stat['cpuTicks'],
            ioCharacters: $ioCharacters,
        );
    }

    /**
     * @return array{cpuTicks: int, startTimeTicks: int}|null
     */
    private function parseStat(
        string $contents,
        int $expectedProcessId,
        ?int $expectedParentProcessId,
    ): ?array {
        if (preg_match('/^([0-9]+) \(/', $contents, $matches) !== 1) {
            return null;
        }

        $parsedProcessId = $this->parsePositiveInteger($matches[1]);
        $closingParenthesisPosition = strrpos($contents, ')');
        if ($parsedProcessId !== $expectedProcessId || $closingParenthesisPosition === false) {
            return null;
        }

        $fieldsContents = trim(substr($contents, $closingParenthesisPosition + 1));
        $fields = preg_split('/\s+/', $fieldsContents);
        if ($fields === false || count($fields) !== 50 || preg_match('/^[A-Za-z]$/', $fields[0]) !== 1) {
            return null;
        }

        for ($fieldIndex = 1; $fieldIndex <= 5; ++$fieldIndex) {
            if (preg_match('/^-?[0-9]+$/', $fields[$fieldIndex]) !== 1) {
                return null;
            }
        }

        for ($fieldIndex = 6; $fieldIndex <= 10; ++$fieldIndex) {
            if (preg_match('/^[0-9]+$/', $fields[$fieldIndex]) !== 1) {
                return null;
            }
        }

        if (
            $expectedParentProcessId !== null
            && $this->parsePositiveInteger($fields[1]) !== $expectedParentProcessId
        ) {
            return null;
        }

        foreach (array_slice($fields, 13) as $trailingField) {
            if (preg_match('/^-?[0-9]+$/', $trailingField) !== 1) {
                return null;
            }
        }

        $userTicks = $this->parseNonNegativeInteger($fields[11]);
        $systemTicks = $this->parseNonNegativeInteger($fields[12]);
        $cpuTicks = $this->addCounters($userTicks, $systemTicks);
        $startTimeTicks = $this->parseNonNegativeInteger($fields[19]);
        if ($cpuTicks === null || $startTimeTicks === null) {
            return null;
        }

        return [
            'cpuTicks' => $cpuTicks,
            'startTimeTicks' => $startTimeTicks,
        ];
    }

    private function parseIoCharacters(string $contents): ?int
    {
        $matched = preg_match_all(
            '/^(rchar|wchar):[ \t]*([0-9]+)[ \t]*\r?$/m',
            $contents,
            $matches,
            PREG_SET_ORDER,
        );
        if ($matched === false) {
            return null;
        }

        $counters = [];
        foreach ($matches as $match) {
            if (isset($counters[$match[1]])) {
                return null;
            }

            $counters[$match[1]] = $this->parseNonNegativeInteger($match[2]);
        }

        if (!array_key_exists('rchar', $counters) || !array_key_exists('wchar', $counters)) {
            return null;
        }

        return $this->addCounters($counters['rchar'], $counters['wchar']);
    }

    /**
     * Собирает direct children всех task TID процесса.
     *
     * Дочерний процесс привязан к создавшему его thread, поэтому чтение только
     * leader TID теряет процессы, запущенные из worker thread. Любая гонка
     * списка TID/children или дублированный child делает выборку недостоверной.
     *
     * @return list<int>|null
     */
    private function collectChildProcessIds(int $processId): ?array
    {
        $taskPath = sprintf('%s/%d/task', $this->procRoot, $processId);
        $threadDirectory = $this->filesystem->listDirectory($taskPath);
        if ($threadDirectory === null) {
            return null;
        }

        $threadIds = $this->parseThreadIds($threadDirectory->entries, $processId);
        if ($threadIds === null) {
            return null;
        }

        $childProcessIds = [];
        foreach ($threadIds as $threadId) {
            $childrenContents = $this->filesystem->read(sprintf(
                '%s/%d/children',
                $taskPath,
                $threadId,
            ));
            if ($childrenContents === null) {
                return null;
            }

            $threadChildProcessIds = $this->parseChildProcessIds($childrenContents);
            if ($threadChildProcessIds === null) {
                return null;
            }

            foreach ($threadChildProcessIds as $childProcessId) {
                if ($childProcessId === $processId || isset($childProcessIds[$childProcessId])) {
                    return null;
                }

                $childProcessIds[$childProcessId] = $childProcessId;
            }
        }

        sort($childProcessIds, SORT_NUMERIC);

        return $childProcessIds;
    }

    /**
     * @param list<string> $entries
     *
     * @return list<int>|null
     */
    private function parseThreadIds(array $entries, int $leaderProcessId): ?array
    {
        $threadIds = $this->parseUniquePositiveIntegers($entries);
        if ($threadIds === null || !in_array($leaderProcessId, $threadIds, true)) {
            return null;
        }

        return $threadIds;
    }

    /**
     * @return list<int>|null
     */
    private function parseChildProcessIds(string $contents): ?array
    {
        $trimmedContents = trim($contents);
        if ($trimmedContents === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $trimmedContents);
        if ($parts === false) {
            return null;
        }

        return $this->parseUniquePositiveIntegers($parts);
    }

    /**
     * @param list<string> $values
     *
     * @return list<int>|null
     */
    private function parseUniquePositiveIntegers(array $values): ?array
    {
        $integers = [];
        foreach ($values as $value) {
            $integer = $this->parsePositiveInteger($value);
            if ($integer === null || isset($integers[$integer])) {
                return null;
            }

            $integers[$integer] = $integer;
        }

        sort($integers, SORT_NUMERIC);

        return $integers;
    }

    private function parsePositiveInteger(string $value): ?int
    {
        $parsed = $this->parseNonNegativeInteger($value);

        return $parsed !== null && $parsed > 0 ? $parsed : null;
    }

    private function parseNonNegativeInteger(string $value): ?int
    {
        if ($value === '' || preg_match('/^[0-9]+$/', $value) !== 1) {
            return null;
        }

        $normalized = ltrim($value, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $maximum = (string) PHP_INT_MAX;
        if (
            strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)
        ) {
            return null;
        }

        return (int) $normalized;
    }

    private function addCounters(?int $left, ?int $right): ?int
    {
        if ($left === null || $right === null || $left > PHP_INT_MAX - $right) {
            return null;
        }

        return $left + $right;
    }

    private function resolveResult(
        ProcessLivenessSnapshotDto $snapshot,
        ?ProcessLivenessSnapshotDto $previousSnapshot,
    ): ProcessLivenessActiveProbeResultDto
        | ProcessLivenessInactiveProbeResultDto
        | ProcessLivenessUnknownProbeResultDto {
        if ($previousSnapshot === null) {
            return new ProcessLivenessActiveProbeResultDto($snapshot);
        }

        $currentProcessIds = array_keys($snapshot->processes);
        $previousProcessIds = array_keys($previousSnapshot->processes);
        sort($currentProcessIds, SORT_NUMERIC);
        sort($previousProcessIds, SORT_NUMERIC);
        if ($currentProcessIds !== $previousProcessIds) {
            return new ProcessLivenessActiveProbeResultDto($snapshot);
        }

        foreach ($snapshot->processes as $processId => $processSnapshot) {
            $previousProcessSnapshot = $previousSnapshot->processes[$processId];
            if ($processSnapshot->startTimeTicks !== $previousProcessSnapshot->startTimeTicks) {
                return new ProcessLivenessActiveProbeResultDto($snapshot);
            }
        }

        $hasCounterGrowth = false;
        foreach ($snapshot->processes as $processId => $processSnapshot) {
            $previousProcessSnapshot = $previousSnapshot->processes[$processId];
            if (
                $processSnapshot->cpuTicks < $previousProcessSnapshot->cpuTicks
                || $processSnapshot->ioCharacters < $previousProcessSnapshot->ioCharacters
            ) {
                return $this->unknownResult();
            }

            if (
                $processSnapshot->cpuTicks > $previousProcessSnapshot->cpuTicks
                || $processSnapshot->ioCharacters > $previousProcessSnapshot->ioCharacters
            ) {
                $hasCounterGrowth = true;
            }
        }

        return $hasCounterGrowth
            ? new ProcessLivenessActiveProbeResultDto($snapshot)
            : new ProcessLivenessInactiveProbeResultDto($snapshot);
    }

    private function unknownResult(): ProcessLivenessUnknownProbeResultDto
    {
        return new ProcessLivenessUnknownProbeResultDto();
    }
}
