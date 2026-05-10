<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Console\Module\Orchestrator\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\RunAgent\RunAgentCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\RunAgent\RunAgentResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Agent\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Prompt\PromptProviderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Console\Module\Orchestrator\Command\RunCommand;

#[CoversClass(RunCommand::class)]
#[CoversClass(RunAgentCommand::class)]
final class RunCommandTest extends TestCase
{
    private RunAgentServiceInterface $agentRunner;
    private PromptProviderInterface $promptProvider;
    private ?ChainRunRequestVo $capturedRequest = null;

    #[Override]
    protected function setUp(): void
    {
        $this->capturedRequest = null;
        $test = $this;

        $this->agentRunner = $this->createMock(RunAgentServiceInterface::class);
        $this->agentRunner
            ->method('run')
            ->willReturnCallback(function (ChainRunRequestVo $request) use ($test): ChainRunResultVo {
                $test->setCapturedRequest($request);

                return ChainRunResultVo::createSuccess(
                    outputText: 'Agent completed successfully',
                    inputTokens: 100,
                    outputTokens: 50,
                    cost: 0.01,
                    model: 'claude-4',
                    turns: 2,
                );
            });

        $this->promptProvider = $this->createMock(PromptProviderInterface::class);
        $this->promptProvider->method('getPrompt')->willReturn('You are a helpful agent.');
    }

    public function setCapturedRequest(ChainRunRequestVo $request): void
    {
        $this->capturedRequest = $request;
    }

    // ──── Basic execution ───────────────────────────────────────────────

    #[Test]
    public function executeWithRequiredOptions(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([
            '--role' => 'system_analyst',
            '--task' => 'Analyze the codebase',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertNotNull($this->capturedRequest);
        self::assertSame('system_analyst', $this->capturedRequest->getRole());
        self::assertSame('Analyze the codebase', $this->capturedRequest->getTask());
    }

    #[Test]
    public function executePassesModelAndWorkingDir(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([
            '--role' => 'dev',
            '--task' => 'Implement feature',
            '--model' => 'gpt-4',
            '--working-dir' => '/tmp/project',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('gpt-4', $this->capturedRequest->getModel());
        self::assertSame('/tmp/project', $this->capturedRequest->getWorkingDir());
    }

    // ──── --timeout ─────────────────────────────────────────────────────

    #[Test]
    public function timeoutDefaultsTo300(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([
            '--role' => 'dev',
            '--task' => 'task',
        ]);

        self::assertSame(300, $this->capturedRequest->getTimeout());
    }

    #[Test]
    public function timeoutPassesCustomValue(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([
            '--role' => 'dev',
            '--task' => 'task',
            '--timeout' => '600',
        ]);

        self::assertSame(600, $this->capturedRequest->getTimeout());
    }

    #[Test]
    public function timeoutZeroMeansNoLimit(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([
            '--role' => 'dev',
            '--task' => 'task',
            '--timeout' => '0',
        ]);

        self::assertSame(0, $this->capturedRequest->getTimeout());
    }

    // ──── --context (valid JSON) ────────────────────────────────────────

    #[Test]
    public function contextPassesJsonObjectAsPreviousContext(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([
            '--role' => 'dev',
            '--task' => 'task',
            '--context' => '{"key":"value","count":42}',
        ]);

        self::assertSame('{"key":"value","count":42}', $this->capturedRequest->getPreviousContext());
    }

    #[Test]
    public function contextPassesJsonStringAsPreviousContext(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([
            '--role' => 'dev',
            '--task' => 'task',
            '--context' => '"plain string context"',
        ]);

        // json_decode возвращает string → берём decoded value
        self::assertSame('plain string context', $this->capturedRequest->getPreviousContext());
    }

    #[Test]
    public function contextPassesJsonArrayAsPreviousContext(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([
            '--role' => 'dev',
            '--task' => 'task',
            '--context' => '["item1","item2"]',
        ]);

        // json_decode возвращает array → берём raw string
        self::assertSame('["item1","item2"]', $this->capturedRequest->getPreviousContext());
    }

    #[Test]
    public function contextNotProvidedYieldsNullPreviousContext(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([
            '--role' => 'dev',
            '--task' => 'task',
        ]);

        self::assertNull($this->capturedRequest->getPreviousContext());
    }

    // ──── --context (invalid JSON) ──────────────────────────────────────

    #[Test]
    public function contextInvalidJsonReturnsFailure(): void
    {
        $tester = $this->createCommandTester();
        $exitCode = $tester->execute([
            '--role' => 'dev',
            '--task' => 'task',
            '--context' => '{invalid json!!!}',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Invalid JSON', $tester->getDisplay());
    }

    #[Test]
    public function contextEmptyStringIsIgnored(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([
            '--role' => 'dev',
            '--task' => 'task',
            '--context' => '',
        ]);

        self::assertNull($this->capturedRequest->getPreviousContext());
    }

    // ──── Error handling ────────────────────────────────────────────────

    #[Test]
    public function executeHandlesAgentError(): void
    {
        $errorRunner = $this->createMock(RunAgentServiceInterface::class);
        $errorRunner->method('run')->willReturn(
            ChainRunResultVo::createError('Agent crashed', 1),
        );
        $promptProvider = $this->createMock(PromptProviderInterface::class);
        $promptProvider->method('getPrompt')->willReturn('prompt');

        $handler = new RunAgentCommandHandler($errorRunner, $promptProvider);

        $application = new Application();
        $application->addCommand(new RunCommand($handler));
        $command = $application->find('app:agent:run');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--role' => 'dev',
            '--task' => 'task',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Agent error: Agent crashed', $tester->getDisplay());
    }

    // ──── Helpers ───────────────────────────────────────────────────────

    private function createCommandTester(): CommandTester
    {
        $handler = new RunAgentCommandHandler($this->agentRunner, $this->promptProvider);

        $application = new Application();
        $application->addCommand(new RunCommand($handler));

        return new CommandTester($application->find('app:agent:run'));
    }
}
