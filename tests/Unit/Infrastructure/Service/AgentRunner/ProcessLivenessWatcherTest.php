<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner;

use Closure;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    Dto\ProcessLivenessActiveProbeResultDto,
    Dto\ProcessLivenessInactiveProbeResultDto,
    Dto\ProcessLivenessPidSnapshotDto,
    Dto\ProcessLivenessSnapshotDto,
    Dto\ProcessLivenessUnknownProbeResultDto,
    ProcessLivenessClockComponentInterface,
    ProcessLivenessProbeComponentInterface,
    ProcessLivenessSleeperComponentInterface,
};
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\ProcessLivenessWatcher;

/**
 * Детерминированные Unit-тесты policy-логики без процессов ОС и реального sleep.
 */
#[CoversClass(ProcessLivenessWatcher::class)]
final class ProcessLivenessWatcherTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC');
        putenv('AGENT_RUNNER_HARD_TIMEOUT_SEC');
    }

    #[Test]
    public function waitForStopsOnlyAfterComparableInactiveSamplesExceedThreshold(): void
    {
        // Arrange
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=1');
        $snapshot = $this->snapshot([42 => [10, 20]]);
        $probe = new ProcessLivenessProbeStub([
            $this->activeResult($snapshot),
            $this->inactiveResult($snapshot),
            $this->inactiveResult($snapshot),
        ]);
        $clock = new ProcessLivenessClockFake();
        $process = new ProcessLivenessProcessDouble();
        $watcher = $this->watcher($probe, $clock);

        // Act
        $completed = $watcher->waitFor($process);

        // Assert
        self::assertFalse($completed);
        self::assertSame(1, $process->stopCalls);
        self::assertFalse($process->isRunning());
        self::assertSame(3, $probe->calls);
        self::assertSame(3, $process->checkTimeoutCalls);
        self::assertNull($probe->previousSnapshots[0]);
        self::assertSame($snapshot, $probe->previousSnapshots[1]);
    }

    #[Test]
    public function waitForTreatsTopologyChangeAsActivityAndDoesNotStopProcess(): void
    {
        // Arrange
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=1');
        $parentSnapshot = $this->snapshot([42 => [10, 20]]);
        $parentAndChildSnapshot = $this->snapshot([
            42 => [10, 20],
            84 => [1, 2],
        ]);
        $probe = new ProcessLivenessProbeStub([
            $this->activeResult($parentSnapshot),
            $this->activeResult($parentAndChildSnapshot),
            $this->inactiveResult($parentAndChildSnapshot),
        ]);
        $clock = new ProcessLivenessClockFake();
        $process = new ProcessLivenessProcessDouble();
        $watcher = $this->watcher(
            probe: $probe,
            clock: $clock,
            onSleep: static function (int $calls) use ($process): void {
                if ($calls === 3) {
                    $process->finish();
                }
            },
        );

        // Act
        $completed = $watcher->waitFor($process);

        // Assert
        self::assertTrue($completed);
        self::assertSame(0, $process->stopCalls);
        self::assertSame(3, $probe->calls);
    }

    #[Test]
    public function waitForKeepsUnknownModePermanentForCurrentWait(): void
    {
        // Arrange
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=1');
        $snapshot = $this->snapshot([42 => [10, 20]]);
        $probe = new ProcessLivenessProbeStub([
            $this->activeResult($snapshot),
            $this->unknownResult(),
            $this->inactiveResult($snapshot),
        ]);
        $clock = new ProcessLivenessClockFake();
        $process = new ProcessLivenessProcessDouble();
        $watcher = $this->watcher(
            probe: $probe,
            clock: $clock,
            onSleep: static function (int $calls) use ($process): void {
                if ($calls === 4) {
                    $process->finish();
                }
            },
        );

        // Act
        $completed = $watcher->waitFor($process);

        // Assert
        self::assertTrue($completed);
        self::assertSame(0, $process->stopCalls);
        self::assertSame(2, $probe->calls, 'UNKNOWN must disable later probes for this waitFor().');
        self::assertSame(4, $process->checkTimeoutCalls, 'Hard cap check must continue in UNKNOWN mode.');
    }

    #[Test]
    public function waitForLiveProcessWithoutPidUsesHardCapOnly(): void
    {
        // Arrange
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=1');
        $probe = new ProcessLivenessProbeStub([]);
        $clock = new ProcessLivenessClockFake();
        $process = new ProcessLivenessProcessDouble(processId: null);
        $watcher = $this->watcher(
            probe: $probe,
            clock: $clock,
            onSleep: static function (int $calls) use ($process): void {
                if ($calls === 3) {
                    $process->finish();
                }
            },
        );

        // Act
        $completed = $watcher->waitFor($process);

        // Assert
        self::assertTrue($completed);
        self::assertSame(0, $probe->calls);
        self::assertSame(0, $process->stopCalls);
        self::assertSame(3, $process->checkTimeoutCalls);
    }

    #[Test]
    public function waitForExitBetweenRunningCheckAndPidReturnsNaturally(): void
    {
        // Arrange
        $probe = new ProcessLivenessProbeStub([]);
        $clock = new ProcessLivenessClockFake();
        $process = new ProcessLivenessProcessDouble();
        $process->onGetPid = static function () use ($process): ?int {
            $process->finish();

            return null;
        };
        $watcher = $this->watcher($probe, $clock);

        // Act
        $completed = $watcher->waitFor($process);

        // Assert
        self::assertTrue($completed);
        self::assertSame(0, $probe->calls);
        self::assertSame(0, $process->stopCalls);
    }

    #[Test]
    public function waitForProcessDeathDuringProbeReturnsNaturally(): void
    {
        // Arrange
        $clock = new ProcessLivenessClockFake();
        $process = new ProcessLivenessProcessDouble();
        $probe = new ProcessLivenessProbeStub([
            static function () use ($process): ProcessLivenessUnknownProbeResultDto {
                $process->finish();

                return new ProcessLivenessUnknownProbeResultDto();
            },
        ]);
        $watcher = $this->watcher($probe, $clock);

        // Act
        $completed = $watcher->waitFor($process);

        // Assert
        self::assertTrue($completed);
        self::assertSame(0, $process->stopCalls);
        self::assertSame(1, $probe->calls);
    }

    #[Test]
    public function waitForNaturalExitImmediatelyBeforeIdleStopReturnsNaturally(): void
    {
        // Arrange
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=1');
        $snapshot = $this->snapshot([42 => [10, 20]]);
        $probe = new ProcessLivenessProbeStub([
            $this->activeResult($snapshot),
            $this->inactiveResult($snapshot),
            $this->inactiveResult($snapshot),
        ]);
        $process = new ProcessLivenessProcessDouble();
        $clock = new ProcessLivenessClockFake(static function (float $time) use ($process): void {
            if ($time > 1.0) {
                $process->finish();
            }
        });
        $watcher = $this->watcher($probe, $clock);

        // Act
        $completed = $watcher->waitFor($process);

        // Assert
        self::assertTrue($completed);
        self::assertSame(0, $process->stopCalls);
        self::assertSame(3, $probe->calls);
    }

    #[Test]
    public function waitForUnknownModeStillPropagatesHardCapException(): void
    {
        // Arrange
        $probe = new ProcessLivenessProbeStub([$this->unknownResult()]);
        $clock = new ProcessLivenessClockFake();
        $process = new ProcessLivenessProcessDouble(timeoutOnCheckCall: 3);
        $watcher = $this->watcher($probe, $clock);

        // Act + Assert
        try {
            $watcher->waitFor($process);
            self::fail('Hard-cap exception must be propagated.');
        } catch (ProcessTimedOutException $exception) {
            self::assertSame($process, $exception->getProcess());
        }

        self::assertSame(3, $process->checkTimeoutCalls);
        self::assertSame(1, $probe->calls);
        self::assertFalse($process->isRunning());
    }

    #[Test]
    public function waitForPropagatesSameUnexpectedProbeThrowable(): void
    {
        // Arrange
        $expected = new LogicException('probe programming error');
        $probe = new ProcessLivenessProbeStub([$expected]);
        $clock = new ProcessLivenessClockFake();
        $process = new ProcessLivenessProcessDouble();
        $watcher = $this->watcher($probe, $clock);

        // Act + Assert
        try {
            $watcher->waitFor($process);
            self::fail('Unexpected probe Throwable must be propagated.');
        } catch (LogicException $actual) {
            self::assertSame($expected, $actual);
        } finally {
            $process->finish();
        }
    }

    #[Test]
    public function resolveHardCapReturnsRequestTimeoutWhenHigherThanEnv(): void
    {
        // Arrange
        putenv('AGENT_RUNNER_HARD_TIMEOUT_SEC=100');
        $watcher = $this->watcher(new ProcessLivenessProbeStub([]), new ProcessLivenessClockFake());

        // Act
        $hardCap = $watcher->resolveHardCap(200);

        // Assert
        self::assertSame(200, $hardCap);
    }

    #[Test]
    public function resolveHardCapReturnsEnvCapWhenHigherThanRequest(): void
    {
        // Arrange
        putenv('AGENT_RUNNER_HARD_TIMEOUT_SEC=1800');
        $watcher = $this->watcher(new ProcessLivenessProbeStub([]), new ProcessLivenessClockFake());

        // Act
        $hardCap = $watcher->resolveHardCap(300);

        // Assert
        self::assertSame(1800, $hardCap);
    }

    #[Test]
    public function resolveHardCapUsesDefaultAndIgnoresNonNumericEnv(): void
    {
        // Arrange
        $watcher = $this->watcher(new ProcessLivenessProbeStub([]), new ProcessLivenessClockFake());

        // Act + Assert
        self::assertSame(1800, $watcher->resolveHardCap(null));

        putenv('AGENT_RUNNER_HARD_TIMEOUT_SEC=not-a-number');
        self::assertSame(1800, $watcher->resolveHardCap(null));
    }

    #[Test]
    public function getIdleThresholdReadsOverrideAndDefault(): void
    {
        // Arrange
        $watcher = $this->watcher(new ProcessLivenessProbeStub([]), new ProcessLivenessClockFake());

        // Act + Assert
        self::assertSame(60, $watcher->getIdleThreshold());

        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=45');
        self::assertSame(45, $watcher->getIdleThreshold());
    }

    private function watcher(
        ProcessLivenessProbeComponentInterface $probe,
        ProcessLivenessClockFake $clock,
        ?Closure $onSleep = null,
    ): ProcessLivenessWatcher {
        return new ProcessLivenessWatcher(
            probe: $probe,
            clock: $clock,
            sleeper: new ProcessLivenessSleeperFake($clock, $onSleep),
        );
    }

    /**
     * @param array<int, array{0: int, 1: int}> $countersByProcessId
     */
    private function snapshot(array $countersByProcessId): ProcessLivenessSnapshotDto
    {
        $processes = [];
        foreach ($countersByProcessId as $processId => [$cpuTicks, $ioCharacters]) {
            $processes[$processId] = new ProcessLivenessPidSnapshotDto(
                processId: $processId,
                startTimeTicks: $processId * 100,
                cpuTicks: $cpuTicks,
                ioCharacters: $ioCharacters,
            );
        }

        return new ProcessLivenessSnapshotDto($processes);
    }

    private function activeResult(
        ProcessLivenessSnapshotDto $snapshot,
    ): ProcessLivenessActiveProbeResultDto {
        return new ProcessLivenessActiveProbeResultDto($snapshot);
    }

    private function inactiveResult(
        ProcessLivenessSnapshotDto $snapshot,
    ): ProcessLivenessInactiveProbeResultDto {
        return new ProcessLivenessInactiveProbeResultDto($snapshot);
    }

    private function unknownResult(): ProcessLivenessUnknownProbeResultDto
    {
        return new ProcessLivenessUnknownProbeResultDto();
    }
}

/**
 * Детерминированный double Symfony Process для policy Unit-тестов.
 */
final class ProcessLivenessProcessDouble extends Process
{
    public int $checkTimeoutCalls = 0;
    public int $stopCalls = 0;
    public ?Closure $onGetPid = null;
    private bool $running = false;

    public function __construct(
        private readonly ?int $processId = 42,
        private readonly ?int $timeoutOnCheckCall = null,
    ) {
        parent::__construct(['process-double']);
        $this->running = true;
    }

    #[Override]
    public function isRunning(): bool
    {
        return $this->running;
    }

    #[Override]
    public function getPid(): ?int
    {
        if ($this->onGetPid !== null) {
            return ($this->onGetPid)();
        }

        return $this->processId;
    }

    #[Override]
    public function checkTimeout(): void
    {
        ++$this->checkTimeoutCalls;
        if ($this->timeoutOnCheckCall === $this->checkTimeoutCalls) {
            $this->running = false;

            throw new ProcessTimedOutException($this, ProcessTimedOutException::TYPE_GENERAL);
        }
    }

    #[Override]
    public function stop(float $timeout = 10, ?int $signal = null): ?int
    {
        ++$this->stopCalls;
        $this->running = false;

        return 143;
    }

    public function finish(): void
    {
        $this->running = false;
    }
}

/**
 * Управляемые монотонные часы.
 */
final class ProcessLivenessClockFake implements ProcessLivenessClockComponentInterface
{
    private float $time = 0.0;

    public function __construct(private readonly ?Closure $onNow = null)
    {
    }

    #[Override]
    public function now(): float
    {
        if ($this->onNow !== null) {
            ($this->onNow)($this->time);
        }

        return $this->time;
    }

    public function advance(float $seconds): void
    {
        $this->time += $seconds;
    }
}

/**
 * Ожидание без реального sleep, продвигающее fake clock.
 */
final class ProcessLivenessSleeperFake implements ProcessLivenessSleeperComponentInterface
{
    private int $calls = 0;

    public function __construct(
        private readonly ProcessLivenessClockFake $clock,
        private readonly ?Closure $onSleep,
    ) {
    }

    #[Override]
    public function sleep(int $microseconds): void
    {
        ++$this->calls;
        $this->clock->advance(0.6);

        if ($this->onSleep !== null) {
            ($this->onSleep)($this->calls);
        }
    }
}

/**
 * Очередь результатов/ошибок liveness-пробы.
 */
final class ProcessLivenessProbeStub implements ProcessLivenessProbeComponentInterface
{
    public int $calls = 0;

    /** @var list<ProcessLivenessSnapshotDto|null> */
    public array $previousSnapshots = [];

    public function __construct(
        /**
         * @var list<ProcessLivenessActiveProbeResultDto|ProcessLivenessInactiveProbeResultDto
         *     |ProcessLivenessUnknownProbeResultDto|Closure|\Throwable>
         */
        private array $outcomes,
    ) {
    }

    #[Override]
    public function probe(
        int $processId,
        ?ProcessLivenessSnapshotDto $previousSnapshot,
    ): ProcessLivenessActiveProbeResultDto
        | ProcessLivenessInactiveProbeResultDto
        | ProcessLivenessUnknownProbeResultDto {
        ++$this->calls;
        $this->previousSnapshots[] = $previousSnapshot;
        $outcome = array_shift($this->outcomes);

        if ($outcome instanceof \Throwable) {
            throw $outcome;
        }

        if ($outcome instanceof Closure) {
            return $outcome();
        }

        if (
            !$outcome instanceof ProcessLivenessActiveProbeResultDto
            && !$outcome instanceof ProcessLivenessInactiveProbeResultDto
            && !$outcome instanceof ProcessLivenessUnknownProbeResultDto
        ) {
            throw new LogicException('No liveness probe result configured.');
        }

        return $outcome;
    }
}
