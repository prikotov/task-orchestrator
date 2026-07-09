<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\ProcessLivenessWatcher;

/**
 * Unit-тесты для ProcessLivenessWatcher — вынесенной из раннеров
 * liveness-логики (раньше дублировалась в Pi/Codex runners).
 */
#[CoversClass(ProcessLivenessWatcher::class)]
final class ProcessLivenessWatcherTest extends TestCase
{
    /** @var list<string> */
    private array $fixtureFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtureFiles as $fixtureFile) {
            @unlink($fixtureFile);
        }
        $this->fixtureFiles = [];

        // Очистка env-переменных liveness
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC');
        putenv('AGENT_RUNNER_HARD_TIMEOUT_SEC');
    }

    #[Test]
    public function waitForKillsIdleProcess(): void
    {
        // Процесс спит без CPU/IO — liveness должна убить за idle-threshold (2с)
        // и вернуть false (остановлен по idle).
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=2');
        $watcher = new ProcessLivenessWatcher();

        $process = new Process([$this->createExecutableFixture('liveness_idle_', <<<'PHP'
sleep(120);
PHP
        )]);
        $process->setTimeout(1800); // hard cap — не должен сработать раньше idle

        $start = microtime(true);
        $process->start();
        $completed = $watcher->waitFor($process);
        $elapsed = microtime(true) - $start;

        self::assertFalse($completed, 'idle process must be reported as killed (false)');
        self::assertFalse($process->isRunning(), 'process must be stopped');
        // Kill должен сработать заметно быстрее sleep(120) — в районе idle-threshold + grace.
        self::assertLessThan(30.0, $elapsed, 'idle process must be killed fast, not waited full sleep');
    }

    #[Test]
    public function waitForLetsActiveProcessSurvive(): void
    {
        // Активный процесс (CPU/IO растут) — liveness НЕ должна убивать: он завершается сам.
        // idle-threshold намеренно мал (2с), чтобы тест проверял именно «активный дожил».
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=2');
        $watcher = new ProcessLivenessWatcher();

        $process = new Process([$this->createExecutableFixture('liveness_active_', <<<'PHP'
for ($i = 0; $i < 30; $i++) {
    fwrite(STDOUT, "tick $i\n");
    fflush(STDOUT);
    usleep(100000);
}
PHP
        )]);
        $process->setTimeout(1800);

        $process->start();
        $completed = $watcher->waitFor($process);

        self::assertTrue($completed, 'active process must NOT be killed: it finishes by itself');
        self::assertTrue($process->isSuccessful(), 'process must exit 0');
    }

    #[Test]
    public function waitForLetsProcessSurviveWhenChildIsActive(): void
    {
        // Codex/agent pattern: сам процесс idle (ep_poll / pcntl_wait), но spawn'ит
        // active children (tool calls: find/grep/sed). До фикса liveness видела только
        // direct PID → ложно kill'ила активный процесс с tool calls. С фиксом children
        // CPU/IO учитываются → процесс дорабатывает.
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=2');
        $watcher = new ProcessLivenessWatcher();

        $process = new Process([$this->createExecutableFixture('liveness_child_', <<<'PHP'
$child = pcntl_fork();
if ($child === 0) {
    for ($i = 0; $i < 40; $i++) {
        fwrite(STDOUT, "tick $i\n");
        fflush(STDOUT);
        usleep(100000);
    }
    exit(0);
}
pcntl_wait($status);
PHP
        )]);
        $process->setTimeout(1800);

        $process->start();
        $completed = $watcher->waitFor($process);

        self::assertTrue($completed, 'process with active child must NOT be killed (children CPU/IO counts)');
        self::assertTrue($process->isSuccessful());
    }

    #[Test]
    public function resolveHardCapReturnsRequestTimeoutWhenHigherThanEnv(): void
    {
        putenv('AGENT_RUNNER_HARD_TIMEOUT_SEC=100');
        $watcher = new ProcessLivenessWatcher();

        self::assertSame(200, $watcher->resolveHardCap(200), 'request-timeout wins when > env-cap');
    }

    #[Test]
    public function resolveHardCapReturnsEnvCapWhenHigherThanRequest(): void
    {
        putenv('AGENT_RUNNER_HARD_TIMEOUT_SEC=1800');
        $watcher = new ProcessLivenessWatcher();

        self::assertSame(1800, $watcher->resolveHardCap(300), 'env-cap wins when > request-timeout');
    }

    #[Test]
    public function resolveHardCapUsesDefaultWhenEnvNotSet(): void
    {
        // AGENT_RUNNER_HARD_TIMEOUT_SEC не задан (tearDown сбросил) → default 1800
        $watcher = new ProcessLivenessWatcher();

        self::assertSame(1800, $watcher->resolveHardCap(300), 'default cap is 1800');
        self::assertSame(1800, $watcher->resolveHardCap(null), 'null request-timeout → default cap');
    }

    #[Test]
    public function getIdleThresholdReadsEnvOverride(): void
    {
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=45');
        $watcher = new ProcessLivenessWatcher();

        self::assertSame(45, $watcher->getIdleThreshold());
    }

    #[Test]
    public function getIdleThresholdUsesDefaultWhenEnvNotSet(): void
    {
        $watcher = new ProcessLivenessWatcher();

        self::assertSame(60, $watcher->getIdleThreshold(), 'default idle threshold is 60');
    }

    #[Test]
    public function resolveHardCapIgnoresNonNumericEnv(): void
    {
        putenv('AGENT_RUNNER_HARD_TIMEOUT_SEC=not-a-number');
        $watcher = new ProcessLivenessWatcher();

        self::assertSame(1800, $watcher->resolveHardCap(null), 'non-numeric env → default 1800');
    }

    private function createExecutableFixture(string $prefix, string $script): string
    {
        $fixtureFile = tempnam(sys_get_temp_dir(), $prefix);
        if ($fixtureFile === false) {
            self::fail('Unable to create temporary liveness fixture.');
        }

        file_put_contents($fixtureFile, "#!/usr/bin/env php\n<?php\n" . $script);
        chmod($fixtureFile, 0700);
        $this->fixtureFiles[] = $fixtureFile;

        return $fixtureFile;
    }
}
