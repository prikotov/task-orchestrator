<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Infrastructure\Service\AgentRunner;

use Error;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use TaskOrchestrator\Common\Kernel;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    Dto\ProcessLivenessActiveProbeResultDto,
    Dto\ProcessLivenessInactiveProbeResultDto,
    Dto\ProcessLivenessSnapshotDto,
    Dto\ProcessLivenessUnknownProbeResultDto,
    ProcessLivenessClockComponent,
    ProcessLivenessProbeComponentInterface,
    ProcessLivenessSleeperComponent,
};
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\CodexAgentRunnerService;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\CodexJsonlParser;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Lifecycle\RunAgentProcessLifecycleService;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\PiAgentRunnerService;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\PiJsonlParser;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\ProcessLivenessWatcher;
use Throwable;
use TypeError;

/**
 * Fail-fast runner cleanup: исходная ошибка пробы выходит после немедленного stop().
 */
#[CoversClass(PiAgentRunnerService::class)]
#[CoversClass(CodexAgentRunnerService::class)]
final class AgentRunnerProbeErrorCleanupIntegrationTest extends KernelTestCase
{
    /** @var list<string> */
    private array $fixtureFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel(['environment' => 'test', 'debug' => false]);

        if (PHP_OS_FAMILY !== 'Linux' || !is_dir('/proc/self')) {
            self::markTestSkipped('Linux procfs is required to assert immediate PID cleanup.');
        }

        putenv('CODEX_HTTP_PROXY');
    }

    protected function tearDown(): void
    {
        try {
            putenv('CODEX_HTTP_PROXY');

            foreach ($this->fixtureFiles as $fixtureFile) {
                unlink($fixtureFile);
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
    #[DataProvider('runnerAndThrowableProvider')]
    public function runProbeThrowableStopsAgentAndPropagatesSameObject(
        string $runnerName,
        string $throwableClass,
    ): void {
        // Arrange
        $expected = $this->throwable($throwableClass);
        $probe = new ThrowingProcessLivenessProbe($expected);
        $watcher = new ProcessLivenessWatcher(
            probe: $probe,
            clock: new ProcessLivenessClockComponent(),
            sleeper: new ProcessLivenessSleeperComponent(),
        );
        $runner = $runnerName === 'pi'
            ? new PiAgentRunnerService(new PiJsonlParser(), new RunAgentProcessLifecycleService($watcher))
            : new CodexAgentRunnerService(new CodexJsonlParser(), new RunAgentProcessLifecycleService($watcher));
        $command = $this->createExecutableFixture($runnerName);

        // Act + Assert
        try {
            $runner->run(new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                command: [$command],
                timeout: 30,
            ));
            self::fail('Probe Throwable must not be converted into AgentResultVo.');
        } catch (Throwable $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertNotNull($probe->processId);
        clearstatcache(true, sprintf('/proc/%d', $probe->processId));
        self::assertDirectoryDoesNotExist(
            sprintf('/proc/%d', $probe->processId),
            'Agent PID must be dead immediately after run(), without waiting for GC.',
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string<Throwable>}>
     */
    public static function runnerAndThrowableProvider(): iterable
    {
        foreach (['pi', 'codex'] as $runnerName) {
            yield $runnerName . ' Error' => [$runnerName, Error::class];
            yield $runnerName . ' TypeError' => [$runnerName, TypeError::class];
            yield $runnerName . ' LogicException' => [$runnerName, LogicException::class];
            yield $runnerName . ' RuntimeException' => [$runnerName, RuntimeException::class];
        }
    }

    /**
     * @param class-string<Throwable> $throwableClass
     */
    private function throwable(string $throwableClass): Throwable
    {
        return new $throwableClass('liveness probe failed');
    }

    private function createExecutableFixture(string $runnerName): string
    {
        $fixtureFile = tempnam(sys_get_temp_dir(), $runnerName . '_cleanup_');
        if ($fixtureFile === false) {
            self::fail('Unable to create runner cleanup fixture.');
        }

        file_put_contents($fixtureFile, "#!/usr/bin/env php\n<?php\nsleep(120);\n");
        chmod($fixtureFile, 0700);
        $this->fixtureFiles[] = $fixtureFile;

        return $fixtureFile;
    }
}

/**
 * Probe double that records the live PID immediately before fail-fast Throwable.
 */
final class ThrowingProcessLivenessProbe implements ProcessLivenessProbeComponentInterface
{
    public ?int $processId = null;

    public function __construct(private readonly Throwable $throwable)
    {
    }

    #[Override]
    public function probe(
        int $processId,
        ?ProcessLivenessSnapshotDto $previousSnapshot,
    ): ProcessLivenessActiveProbeResultDto
        | ProcessLivenessInactiveProbeResultDto
        | ProcessLivenessUnknownProbeResultDto {
        $this->processId = $processId;

        throw $this->throwable;
    }
}
