<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Infrastructure\Service\AgentRunner;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Kernel;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    Dto\ProcessLivenessActiveProbeResultDto,
    ProcFilesystemComponent,
    ProcessLivenessClockComponent,
    ProcessLivenessProbeLinuxProcfsComponent,
    ProcessLivenessSleeperComponent,
};
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\ProcessLivenessWatcher;

/**
 * Реальный regression-контракт: child из Node worker thread виден liveness-пробе.
 */
#[CoversClass(ProcessLivenessWatcher::class)]
#[CoversClass(ProcessLivenessProbeLinuxProcfsComponent::class)]
final class ProcessLivenessWatcherNodeWorkerIntegrationTest extends KernelTestCase
{
    /** @var list<string> */
    private array $fixtureFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel(['environment' => 'test', 'debug' => false]);

        if (PHP_OS_FAMILY !== 'Linux' || !is_dir('/proc/self/task')) {
            self::markTestSkipped('Linux procfs task entries are required for the Node worker regression.');
        }

        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=1');
    }

    protected function tearDown(): void
    {
        try {
            putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC');

            foreach ($this->fixtureFiles as $fixtureFile) {
                if (file_exists($fixtureFile)) {
                    unlink($fixtureFile);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[Test]
    public function waitForCpuActiveChildSpawnedByNonLeaderThreadDoesNotFalseKillParent(): void
    {
        // Arrange
        $nodeBinary = (new ExecutableFinder())->find('node');
        if ($nodeBinary === null) {
            self::markTestSkipped('Node.js is required for the worker_threads liveness regression.');
        }

        $scriptPath = $this->createFixture('node_worker_', $this->nodeWorkerScript());
        $markerPath = $this->createFixture('node_worker_marker_', '');
        unlink($markerPath);
        $process = new Process([$nodeBinary, $scriptPath, $markerPath]);
        $process->setTimeout(15.0);
        $probe = new ProcessLivenessProbeLinuxProcfsComponent(new ProcFilesystemComponent());
        $watcher = new ProcessLivenessWatcher(
            probe: $probe,
            clock: new ProcessLivenessClockComponent(),
            sleeper: new ProcessLivenessSleeperComponent(),
        );
        $process->start();

        try {
            $marker = $this->waitForMarker($markerPath, $process);
            $parentProcessId = $marker['parentPid'];
            $ownerThreadId = $marker['ownerTid'];
            $childProcessId = $marker['childPid'];
            self::assertSame($process->getPid(), $parentProcessId);
            self::assertNotSame(
                $parentProcessId,
                $ownerThreadId,
                'Fixture child must be owned by a non-leader thread for this regression to be meaningful.',
            );

            $leaderChildren = file_get_contents(sprintf(
                '/proc/%d/task/%d/children',
                $parentProcessId,
                $parentProcessId,
            ));
            self::assertIsString($leaderChildren);
            self::assertNotContains(
                (string) $childProcessId,
                preg_split('/\s+/', trim($leaderChildren), -1, PREG_SPLIT_NO_EMPTY),
                'The child must not be visible in the leader-only children file.',
            );

            $initialProbe = $probe->probe($parentProcessId, null);
            self::assertInstanceOf(ProcessLivenessActiveProbeResultDto::class, $initialProbe);
            self::assertArrayHasKey(
                $childProcessId,
                $initialProbe->snapshot->processes,
                'The all-TID procfs probe must discover the worker-owned child before watcher execution.',
            );

            // Act
            $completed = $watcher->waitFor($process);

            // Assert
            self::assertTrue($completed, 'CPU-active worker-owned child must prevent a false idle stop.');
            self::assertTrue($process->isSuccessful());
            self::assertSame(0, $process->getExitCode());
        } finally {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
    }

    /**
     * @return array{parentPid: int, ownerTid: int, childPid: int}
     */
    private function waitForMarker(string $markerPath, Process $process): array
    {
        $deadline = microtime(true) + 2.0;
        while (!is_file($markerPath) && $process->isRunning() && microtime(true) < $deadline) {
            usleep(10_000);
        }

        self::assertFileExists($markerPath, sprintf(
            "Node worker did not publish its child owner. stderr:\n%s",
            $process->getErrorOutput(),
        ));
        $contents = file_get_contents($markerPath);
        self::assertIsString($contents);

        /** @var array{parentPid: int, ownerTid: int, childPid: int} $marker */
        $marker = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertGreaterThan(0, $marker['parentPid']);
        self::assertGreaterThan(0, $marker['ownerTid']);
        self::assertGreaterThan(0, $marker['childPid']);

        return $marker;
    }

    private function createFixture(string $prefix, string $contents): string
    {
        $fixtureFile = tempnam(sys_get_temp_dir(), $prefix);
        if ($fixtureFile === false) {
            self::fail('Unable to create Node worker liveness fixture.');
        }

        file_put_contents($fixtureFile, $contents);
        $this->fixtureFiles[] = $fixtureFile;

        return $fixtureFile;
    }

    private function nodeWorkerScript(): string
    {
        return <<<'JS'
'use strict';

const fs = require('node:fs');
const {spawn} = require('node:child_process');
const {Worker, isMainThread, parentPort} = require('node:worker_threads');

if (isMainThread) {
    const markerPath = process.argv[2];
    const worker = new Worker(__filename);
    worker.once('message', (message) => {
        fs.writeFileSync(markerPath, JSON.stringify({...message, parentPid: process.pid}));
    });
    worker.once('error', (error) => {
        console.error(error);
        process.exitCode = 2;
    });
} else {
    const busyCode = `
        const until = Date.now() + 4000;
        let value = 0;
        while (Date.now() < until) {
            value = (value + 1) % 1000003;
        }
        process.exit(value < 0 ? 1 : 0);
    `;
    const child = spawn(process.execPath, ['-e', busyCode], {stdio: 'ignore'});
    const deadline = Date.now() + 1500;
    let ownerTid = null;

    while (ownerTid === null && Date.now() < deadline) {
        for (const tid of fs.readdirSync(`/proc/${process.pid}/task`)) {
            let children;
            try {
                children = fs.readFileSync(`/proc/${process.pid}/task/${tid}/children`, 'utf8');
            } catch {
                continue;
            }

            if (children.trim().split(/\s+/).includes(String(child.pid))) {
                ownerTid = Number(tid);
                break;
            }
        }
    }

    if (ownerTid === null) {
        child.kill();
        throw new Error('Unable to find worker-owned child in procfs task entries.');
    }

    parentPort.postMessage({ownerTid, childPid: child.pid});
    child.once('exit', (exitCode) => process.exit(exitCode ?? 3));
}
JS;
    }
}
