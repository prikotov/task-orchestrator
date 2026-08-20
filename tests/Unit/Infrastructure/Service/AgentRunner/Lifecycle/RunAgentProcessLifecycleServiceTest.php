<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Lifecycle;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    Dto\ProcessLivenessInactiveProbeResultDto,
    Dto\ProcessLivenessSnapshotDto,
    ProcessLivenessClockComponent,
    ProcessLivenessClockComponentInterface,
    ProcessLivenessProbeComponentInterface,
    ProcessLivenessProbeUnavailableComponent,
    ProcessLivenessSleeperComponent,
    ProcessLivenessSleeperComponentInterface,
};
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\HttpsProxyBridge;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Lifecycle\RunAgentProcessLifecycleService;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\ProcessLivenessWatcher;

/**
 * Unit-тесты общего жизненного цикла процесса агента (RunAgentProcessLifecycleService).
 *
 * Парсер и buildResult имитируются callbacks: collect-lines для stdout и
 * identity-hook для результата — lifecycle обязан передать в hook завершившийся
 * Process и stderr-tail без интерпретации.
 */
#[CoversClass(RunAgentProcessLifecycleService::class)]
final class RunAgentProcessLifecycleServiceTest extends TestCase
{
    private RunAgentProcessLifecycleService $lifecycle;

    /** @var HttpsProxyBridge|null Мост для очистки в tearDown */
    private ?HttpsProxyBridge $bridgeToCleanup = null;

    /** @var list<string> */
    private array $fixtureFiles = [];

    /** @var list<string> Накопленные feed()-строки последнего run() */
    private array $fedLines = [];

    protected function setUp(): void
    {
        putenv('CODEX_HTTP_PROXY');
        $this->lifecycle = new RunAgentProcessLifecycleService($this->createLivenessWatcher());
    }

    protected function tearDown(): void
    {
        // Очистка моста если тест его создал
        $this->bridgeToCleanup?->stop();
        $this->bridgeToCleanup = null;

        foreach ($this->fixtureFiles as $fixtureFile) {
            @unlink($fixtureFile);
        }

        $this->fixtureFiles = [];
        $this->fedLines = [];

        // Очистка env-переменных
        putenv('CODEX_HTTP_PROXY');
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC');
        putenv('AGENT_RUNNER_HARD_TIMEOUT_SEC');
    }

    // ──── buildProcessEnv: proxy scenarios ───────────────────────────────

    #[Test]
    public function buildProcessEnvWithCodexProxySetsHttpsProxy(): void
    {
        $env = $this->lifecycle->buildProcessEnv([
            'PATH' => '/usr/bin',
            'CODEX_HTTP_PROXY' => 'http://proxy.example.com:8080',
        ]);

        self::assertSame('http://proxy.example.com:8080', $env['HTTPS_PROXY']);
        self::assertSame('/usr/bin', $env['PATH']);
    }

    #[Test]
    public function buildProcessEnvWithoutCodexProxyReturnsEnvUnchanged(): void
    {
        $env = $this->lifecycle->buildProcessEnv([
            'PATH' => '/usr/bin',
            'HOME' => '/home/user',
        ]);

        self::assertArrayNotHasKey('HTTPS_PROXY', $env);
        self::assertSame('/usr/bin', $env['PATH']);
        self::assertSame('/home/user', $env['HOME']);
    }

    #[Test]
    public function buildProcessEnvCodexProxyOverridesExistingHttpsProxy(): void
    {
        $env = $this->lifecycle->buildProcessEnv([
            'PATH' => '/usr/bin',
            'HTTPS_PROXY' => 'http://old-proxy:3128',
            'CODEX_HTTP_PROXY' => 'http://new-proxy:8080',
        ]);

        self::assertSame('http://new-proxy:8080', $env['HTTPS_PROXY']);
    }

    #[Test]
    public function buildProcessEnvEmptyCodexProxyDoesNotOverride(): void
    {
        $env = $this->lifecycle->buildProcessEnv([
            'HTTPS_PROXY' => 'http://existing-proxy:3128',
            'CODEX_HTTP_PROXY' => '',
        ]);

        // Пустой CODEX_HTTP_PROXY не подменяет HTTPS_PROXY
        self::assertSame('http://existing-proxy:3128', $env['HTTPS_PROXY']);
    }

    #[Test]
    public function buildProcessEnvPreservesHttpProxy(): void
    {
        $env = $this->lifecycle->buildProcessEnv([
            'HTTP_PROXY' => 'http://http-proxy:3128',
            'CODEX_HTTP_PROXY' => 'http://codex-proxy:8080',
        ]);

        // HTTP_PROXY не затрагивается — передаётся как есть
        self::assertSame('http://http-proxy:3128', $env['HTTP_PROXY']);
        self::assertSame('http://codex-proxy:8080', $env['HTTPS_PROXY']);
    }

    #[Test]
    public function buildProcessEnvEmptyArrayReturnsEmpty(): void
    {
        $env = $this->lifecycle->buildProcessEnv([]);

        self::assertArrayNotHasKey('HTTPS_PROXY', $env);
        self::assertSame([], $env);
    }

    #[Test]
    public function buildProcessEnvDoesNotOverrideForHttpsProxy(): void
    {
        $env = $this->lifecycle->buildProcessEnv([
            'HTTPS_PROXY' => 'http://existing-proxy:3128',
            'CODEX_HTTP_PROXY' => 'https://user:pass@example.com:8080',
        ]);

        // HTTPS-прокси: мост подменит HTTPS_PROXY в run(), здесь не трогаем
        self::assertSame('http://existing-proxy:3128', $env['HTTPS_PROXY']);
    }

    #[Test]
    public function buildProcessEnvStillOverridesForHttpProxy(): void
    {
        $env = $this->lifecycle->buildProcessEnv([
            'HTTPS_PROXY' => 'http://old-proxy:3128',
            'CODEX_HTTP_PROXY' => 'http://new-proxy:8080',
        ]);

        // HTTP-прокси: подменяем как раньше
        self::assertSame('http://new-proxy:8080', $env['HTTPS_PROXY']);
    }

    // ──── createBridgeIfNeeded: HTTPS-прокси активирует мост ────────────

    #[Test]
    public function createBridgeIfNeededReturnsBridgeForHttpsProxy(): void
    {
        putenv('CODEX_HTTP_PROXY=https://user:pass@example.com:8080');

        $bridge = $this->lifecycle->createBridgeIfNeeded();

        self::assertInstanceOf(HttpsProxyBridge::class, $bridge);
        self::assertTrue($bridge->isRunning());
        self::assertMatchesRegularExpression('#^http://127\.0\.0\.1:\d+$#', $bridge->getLocalProxyUrl());

        // Сохраняем для очистки в tearDown
        $this->bridgeToCleanup = $bridge;
    }

    #[Test]
    public function createBridgeIfNeededReturnsNullForHttpProxy(): void
    {
        putenv('CODEX_HTTP_PROXY=http://proxy.example.com:8080');

        $bridge = $this->lifecycle->createBridgeIfNeeded();

        self::assertNull($bridge);
    }

    #[Test]
    public function createBridgeIfNeededReturnsNullWhenEnvNotSet(): void
    {
        // CODEX_HTTP_PROXY не установлен (tearDown очистит если был)
        $bridge = $this->lifecycle->createBridgeIfNeeded();

        self::assertNull($bridge);
    }

    // ──── buildUserPrompt ────────────────────────────────────────────────

    #[Test]
    public function buildUserPromptIncludesPreviousContextAndTask(): void
    {
        $prompt = $this->lifecycle->buildUserPrompt(new AgentRunRequestVo(
            role: 'test',
            task: 'continue work',
            previousContext: 'Previous step output here',
        ));

        self::assertSame("Previous step output here\n\n[Задача]: continue work", $prompt);
    }

    #[Test]
    public function buildUserPromptContainsOnlyTaskWithoutContext(): void
    {
        $prompt = $this->lifecycle->buildUserPrompt(new AgentRunRequestVo(role: 'test', task: 'do something'));

        self::assertSame('[Задача]: do something', $prompt);
    }

    // ──── resolveSystemPromptPath ────────────────────────────────────────

    #[Test]
    public function resolveSystemPromptPathReturnsPathForExistingFile(): void
    {
        $tmpFile = (string) tempnam(sys_get_temp_dir(), 'lifecycle_sys_');
        file_put_contents($tmpFile, 'role prompt');

        try {
            $path = $this->lifecycle->resolveSystemPromptPath(new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                systemPrompt: $tmpFile,
            ));

            self::assertSame($tmpFile, $path);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function resolveSystemPromptPathReturnsNullForNonFileValue(): void
    {
        $path = $this->lifecycle->resolveSystemPromptPath(new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            systemPrompt: 'plain text, not a file',
        ));

        self::assertNull($path);
    }

    #[Test]
    public function resolveSystemPromptPathReturnsNullWhenNotSet(): void
    {
        $path = $this->lifecycle->resolveSystemPromptPath(new AgentRunRequestVo(role: 'test', task: 'task'));

        self::assertNull($path);
    }

    // ──── run: буферизация stdout ────────────────────────────────────────

    #[Test]
    public function runStreamsChunkedStdoutAndFlushesLastLineWithoutNewline(): void
    {
        $command = $this->createExecutableFixture('lifecycle_stream_', <<<'PHP'
fwrite(STDOUT, "{\"line\":1}\n{\"partial");
fflush(STDOUT);
usleep(10000);
fwrite(STDOUT, "_line\":2}\n{\"line\":3}");
PHP);

        $result = $this->runLifecycle([$command]);

        self::assertFalse($result->isError());
        self::assertSame(['{"line":1}', '{"partial_line":2}', '{"line":3}'], $this->fedLines);
    }

    #[Test]
    public function runHandlesEmptyOutputWithoutCrash(): void
    {
        $command = $this->createExecutableFixture('lifecycle_empty_', <<<'PHP'
exit(0);
PHP);

        $result = $this->runLifecycle([$command]);

        self::assertFalse($result->isError());
        self::assertSame([], $this->fedLines);
    }

    #[Test]
    public function runFeedsStderrTailAndExitCodeToBuildResultHook(): void
    {
        $command = $this->createExecutableFixture('lifecycle_stderr_', <<<'PHP'
fwrite(STDERR, "boom");
exit(9);
PHP);

        $capturedProcess = null;
        $capturedErrorOutput = null;
        $result = $this->runLifecycle(
            [$command],
            buildResult: function (Process $process, string $errorOutput) use (&$capturedProcess, &$capturedErrorOutput): AgentResultVo {
                $capturedProcess = $process;
                $capturedErrorOutput = $errorOutput;

                return AgentResultVo::createError($errorOutput, $process->getExitCode() ?? 1);
            },
        );

        self::assertTrue($result->isError());
        self::assertSame('boom', $result->getErrorMessage());
        self::assertSame(9, $result->getExitCode());
        self::assertInstanceOf(Process::class, $capturedProcess);
        self::assertSame('boom', $capturedErrorOutput);
        self::assertFalse($capturedProcess->isRunning(), 'Процесс должен быть остановлен до buildResult hook.');
    }

    #[Test]
    public function runTruncatesErrorOutputToTailLimit(): void
    {
        $command = $this->createExecutableFixture('lifecycle_tail_', <<<'PHP'
fwrite(STDERR, str_repeat('a', 100000));
exit(2);
PHP);

        $result = $this->runLifecycle(
            [$command],
            buildResult: static fn (Process $process, string $errorOutput): AgentResultVo => AgentResultVo::createError($errorOutput, 2),
        );

        // stderr-tail обрезан до ERROR_OUTPUT_TAIL_BYTES (последние 65536 байт)
        self::assertSame(65536, strlen($result->getErrorMessage()));
        self::assertSame(str_repeat('a', 65536), $result->getErrorMessage());
    }

    #[Test]
    public function runUsesWorkingDirFromRequest(): void
    {
        $workingDir = sys_get_temp_dir();
        $command = $this->createExecutableFixture('lifecycle_cwd_', <<<'PHP'
fwrite(STDOUT, getcwd() . "\n");
PHP);

        $result = $this->runLifecycle([$command], workingDir: $workingDir);

        self::assertFalse($result->isError());
        self::assertSame([$workingDir], $this->fedLines);
    }

    #[Test]
    public function runResetsParserBeforeStream(): void
    {
        $command = $this->createExecutableFixture('lifecycle_reset_', <<<'PHP'
fwrite(STDOUT, "{\"line\":1}\n");
PHP);

        $resetCalls = 0;
        $result = $this->runLifecycle(
            [$command],
            resetParser: static function () use (&$resetCalls): void {
                ++$resetCalls;
            },
        );

        self::assertFalse($result->isError());
        self::assertSame(1, $resetCalls);
        self::assertSame(['{"line":1}'], $this->fedLines);
    }

    // ──── run: proxy env ─────────────────────────────────────────────────

    #[Test]
    public function runAppliesHttpProxyEnvironmentWithoutBridge(): void
    {
        // http://-схема: мост не запускается, но env подменяется
        // (HTTPS_PROXY для процесса ← CODEX_HTTP_PROXY).
        putenv('CODEX_HTTP_PROXY=http://proxy.example.com:8080');

        $command = $this->createExecutableFixture('lifecycle_proxy_env_', <<<'PHP'
exit(getenv('HTTPS_PROXY') === 'http://proxy.example.com:8080' ? 0 : 7);
PHP);

        $result = $this->runLifecycle([$command]);

        // exit(7) — если env не применился, hook вернёт ошибку по exit-коду
        self::assertFalse($result->isError());
    }

    #[Test]
    public function runReturnsErrorWhenProxyPreparationFails(): void
    {
        // Невалидный https:// URL (без порта) → мост падает при start();
        // lifecycle обязан вернуть AgentResultVo::createError, не исключение.
        putenv('CODEX_HTTP_PROXY=https://user:pass@example.com');

        $command = $this->createExecutableFixture('lifecycle_proxy_fail_', <<<'PHP'
exit(0);
PHP);

        $result = $this->runLifecycle([$command]);

        self::assertTrue($result->isError());
        self::assertStringContainsString('Failed to prepare proxy environment:', $result->getErrorMessage());
    }

    #[Test]
    public function runAppliesBridgeLocalProxyEnvForHttpsProxy(): void
    {
        // https://-схема: стартует локальный мост, env процесса получает
        // его локальный URL вместо исходного CODEX_HTTP_PROXY. CONNECT к
        // upstream не происходит (фикстура не ходит в сеть), мост останавливается
        // в stopProcessAndBridge после завершения процесса.
        putenv('CODEX_HTTP_PROXY=https://user:pass@example.com:8080');

        $command = $this->createExecutableFixture('lifecycle_bridge_env_', <<<'PHP'
exit(preg_match('#^http://127\.0\.0\.1:\d+$#', (string) getenv('HTTPS_PROXY')) === 1 ? 0 : 7);
PHP);

        $result = $this->runLifecycle([$command]);

        // exit(7) — если env не применился, hook вернёт ошибку по exit-коду
        self::assertFalse($result->isError());
    }

    // ──── run: таймауты ──────────────────────────────────────────────────

    #[Test]
    public function runReturnsErrorOnHardCapTimeout(): void
    {
        putenv('AGENT_RUNNER_HARD_TIMEOUT_SEC=2');

        $command = $this->createExecutableFixture('lifecycle_timeout_', <<<'PHP'
sleep(30);
PHP);

        $result = $this->runLifecycle([$command], requestTimeout: 1);

        self::assertTrue($result->isError());
        self::assertTrue($result->isTimedOut());
        self::assertSame('Agent timed out after 2 seconds (hard cap).', $result->getErrorMessage());
    }

    #[Test]
    public function runReturnsTransientErrorOnConfirmedIdle(): void
    {
        // Probe-стаб всегда подтверждает простой; fake-clock переносит время
        // вперёд без реального sleep — idle-порог превышается мгновенно.
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=330');
        $lifecycle = new RunAgentProcessLifecycleService(
            new ProcessLivenessWatcher(
                probe: new IdleProbeStub(),
                clock: new FastForwardClockStub(),
                sleeper: new NoopSleeperStub(),
            ),
        );

        $command = $this->createExecutableFixture('lifecycle_idle_', <<<'PHP'
sleep(30);
PHP);

        $result = $lifecycle->run(
            new AgentRunRequestVo(role: 'test', task: 'task', command: [$command]),
            'codex',
            static fn (AgentRunRequestVo $request): array => $request->getCommand(),
            static function (): void {
            },
            function (string $line): void {
                $this->fedLines[] = $line;
            },
            static fn (Process $process, string $errorOutput): AgentResultVo => AgentResultVo::createSuccess(''),
        );

        self::assertTrue($result->isError());
        self::assertTrue($result->isTimedOut());
        self::assertSame('Agent idle: no CPU/IO progress for 330 seconds.', $result->getErrorMessage());
    }

    // ──── run: signal-сообщение содержит имя раннера ─────────────────────

    #[Test]
    public function runSignalsRunnerNameInTerminatedBySignalMessageForCodex(): void
    {
        $result = $this->runSignaled('codex');

        self::assertTrue($result->isError());
        self::assertSame('codex process terminated by signal 15.', $result->getErrorMessage());
        self::assertSame(143, $result->getExitCode());
    }

    #[Test]
    public function runSignalsRunnerNameInTerminatedBySignalMessageForPi(): void
    {
        $result = $this->runSignaled('pi');

        self::assertTrue($result->isError());
        self::assertSame('pi process terminated by signal 15.', $result->getErrorMessage());
        self::assertSame(143, $result->getExitCode());
    }

    /**
     * Прогон через probe, бросающий ProcessSignaledException с реального
     * SIGTERM-завершённого процесса (fail-fast проба распространяет Throwable
     * из ProcessLivenessWatcher в catch lifecycle-сервиса).
     */
    private function runSignaled(string $runnerName): AgentResultVo
    {
        $signaledProcess = new Process([PHP_BINARY, '-r', 'posix_kill(getmypid(), SIGTERM); usleep(100000);']);
        $signaledProcess->setTimeout(5);
        $signaledProcess->start();
        try {
            $signaledProcess->wait();
        } catch (ProcessSignaledException) {
            // Процесс завершён сигналом — требуемое состояние для исключения ниже.
        }

        $lifecycle = new RunAgentProcessLifecycleService(
            new ProcessLivenessWatcher(
                probe: new ThrowingProbeStub(new ProcessSignaledException($signaledProcess)),
                clock: new ProcessLivenessClockComponent(),
                sleeper: new ProcessLivenessSleeperComponent(),
            ),
        );

        $command = $this->createExecutableFixture('lifecycle_signal_', <<<'PHP'
sleep(30);
PHP);

        return $lifecycle->run(
            new AgentRunRequestVo(role: 'test', task: 'task', command: [$command], timeout: 30),
            $runnerName,
            static fn (AgentRunRequestVo $request): array => $request->getCommand(),
            static function (): void {
            },
            function (string $line): void {
                $this->fedLines[] = $line;
            },
            static fn (Process $process, string $errorOutput): AgentResultVo => AgentResultVo::createSuccess(''),
        );
    }

    // ──── helpers ────────────────────────────────────────────────────────

    /**
     * Прогоняет lifecycle с collect-lines парсером и дефолтным buildResult-hook.
     *
     * @param list<string> $command
     */
    private function runLifecycle(
        array $command,
        ?callable $resetParser = null,
        ?callable $buildResult = null,
        ?string $workingDir = null,
        int $requestTimeout = 300,
    ): AgentResultVo {
        return $this->lifecycle->run(
            new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                command: $command,
                workingDir: $workingDir,
                timeout: $requestTimeout,
            ),
            'codex',
            static fn (AgentRunRequestVo $request): array => $request->getCommand(),
            $resetParser ?? function (): void {
            },
            function (string $line): void {
                $this->fedLines[] = $line;
            },
            // Дефолтный hook без интерпретации fed-строк: их собирает parser раннера,
            // поэтому успешный run возвращает пустой output.
            $buildResult
            ?? static fn (Process $process, string $errorOutput): AgentResultVo => $process->isSuccessful()
                ? AgentResultVo::createSuccess('')
                : AgentResultVo::createError(
                    $errorOutput !== '' ? $errorOutput : sprintf('exited with code %d.', $process->getExitCode() ?? 1),
                    $process->getExitCode() ?? 1,
                ),
        );
    }

    private function createExecutableFixture(string $prefix, string $script): string
    {
        $fixtureFile = tempnam(sys_get_temp_dir(), $prefix);
        if ($fixtureFile === false) {
            self::fail('Unable to create temporary lifecycle fixture.');
        }

        file_put_contents($fixtureFile, "#!/usr/bin/env php\n<?php\n" . $script);
        chmod($fixtureFile, 0700);
        $this->fixtureFiles[] = $fixtureFile;

        return $fixtureFile;
    }

    private function createLivenessWatcher(): ProcessLivenessWatcher
    {
        return new ProcessLivenessWatcher(
            probe: new ProcessLivenessProbeUnavailableComponent(),
            clock: new ProcessLivenessClockComponent(),
            sleeper: new ProcessLivenessSleeperComponent(),
        );
    }
}

/**
 * Probe-стаб: каждая выборка подтверждает простой (fail-closed idle-stop).
 */
final class IdleProbeStub implements ProcessLivenessProbeComponentInterface
{
    #[Override]
    public function probe(
        int $processId,
        ?ProcessLivenessSnapshotDto $previousSnapshot,
    ): ProcessLivenessInactiveProbeResultDto {
        return new ProcessLivenessInactiveProbeResultDto(new ProcessLivenessSnapshotDto([]));
    }
}

/**
 * Часы, убегающие вперёд: каждая выборка времени на 1000 секунд позже.
 */
final class FastForwardClockStub implements ProcessLivenessClockComponentInterface
{
    private float $time = 1000.0;

    #[Override]
    public function now(): float
    {
        $this->time += 1000.0;

        return $this->time;
    }
}

/**
 * Ожидание без реального sleep — цикл liveness крутится мгновенно.
 */
final class NoopSleeperStub implements ProcessLivenessSleeperComponentInterface
{
    #[Override]
    public function sleep(int $microseconds): void
    {
    }
}

/**
 * Probe-стаб, бросающий заготовленный Throwable (fail-fast).
 */
final class ThrowingProbeStub implements ProcessLivenessProbeComponentInterface
{
    public function __construct(private readonly \Throwable $throwable)
    {
    }

    #[Override]
    public function probe(
        int $processId,
        ?ProcessLivenessSnapshotDto $previousSnapshot,
    ): never {
        throw $this->throwable;
    }
}
