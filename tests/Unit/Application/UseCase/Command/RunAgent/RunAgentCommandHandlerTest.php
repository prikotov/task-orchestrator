<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\UseCase\Command\RunAgent;

use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\RunAgent\RunAgentCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerRegistryPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Prompt\PromptProviderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunAgentCommandHandler::class)]
#[CoversClass(RunAgentCommand::class)]
final class RunAgentCommandHandlerTest extends TestCase
{
    private AgentRunnerRegistryPortInterface $registry;
    private PromptProviderInterface $promptProvider;
    private RunAgentCommandHandler $handler;
    private AgentRunnerPortInterface $runner;

    protected function setUp(): void
    {
        $this->runner = $this->createMock(AgentRunnerPortInterface::class);
        $this->runner->method('getName')->willReturn('pi');

        $this->registry = $this->createMock(AgentRunnerRegistryPortInterface::class);
        $this->promptProvider = $this->createMock(PromptProviderInterface::class);

        $this->handler = new RunAgentCommandHandler(
            $this->registry,
            $this->promptProvider,
        );
    }

    #[Test]
    public function invokeRunsAgentWithCorrectRoleAndPrompt(): void
    {
        $expectedResult = ChainRunResultVo::createFromSuccess(
            outputText: 'Analysis complete',
            inputTokens: 100,
        );

        $this->registry
            ->expects(self::once())
            ->method('get')
            ->with('pi')
            ->willReturn($this->runner);

        $this->promptProvider
            ->expects(self::once())
            ->method('getPrompt')
            ->with('system_analyst')
            ->willReturn('You are a system analyst.');

        $this->runner
            ->expects(self::once())
            ->method('run')
            ->willReturnCallback(function ($request) use ($expectedResult) {
                self::assertSame('You are a system analyst.', $request->getSystemPrompt());
                self::assertSame('system_analyst', $request->getRole());

                return $expectedResult;
            });

        $result = ($this->handler)(new RunAgentCommand(
            role: 'system_analyst',
            task: 'Analyze the code',
        ));

        self::assertSame('Analysis complete', $result->outputText);
        self::assertSame(100, $result->inputTokens);
        self::assertFalse($result->isError);
    }

    #[Test]
    public function invokePassesOptionsToRequest(): void
    {
        $expectedResult = ChainRunResultVo::createFromSuccess(outputText: 'ok');

        $this->registry->method('get')->willReturn($this->runner);
        $this->promptProvider->method('getPrompt')->willReturn('prompt');

        $this->runner
            ->expects(self::once())
            ->method('run')
            ->willReturnCallback(function ($request) use ($expectedResult) {
                self::assertSame('claude-4', $request->getModel());
                self::assertSame('read,write', $request->getTools());
                self::assertSame('/tmp/work', $request->getWorkingDir());

                return $expectedResult;
            });

        $result = ($this->handler)(new RunAgentCommand(
            role: 'test',
            task: 'task',
            model: 'claude-4',
            tools: 'read,write',
            workingDir: '/tmp/work',
        ));

        self::assertFalse($result->isError);
    }

    #[Test]
    public function invokeUsesCustomRunner(): void
    {
        $codexRunner = $this->createMock(AgentRunnerPortInterface::class);
        $expectedResult = ChainRunResultVo::createFromSuccess(outputText: 'codex result');

        $this->registry
            ->expects(self::once())
            ->method('get')
            ->with('codex')
            ->willReturn($codexRunner);

        $this->promptProvider->method('getPrompt')->willReturn('prompt');
        $codexRunner->method('run')->willReturn($expectedResult);

        $result = ($this->handler)(new RunAgentCommand(
            role: 'test',
            task: 'task',
            runner: 'codex',
        ));

        self::assertSame('codex result', $result->outputText);
    }

    #[Test]
    public function invokeMapsErrorResult(): void
    {
        $expectedResult = ChainRunResultVo::createFromError('Agent crashed', 1);

        $this->registry->method('get')->willReturn($this->runner);
        $this->promptProvider->method('getPrompt')->willReturn('prompt');
        $this->runner->method('run')->willReturn($expectedResult);

        $result = ($this->handler)(new RunAgentCommand(
            role: 'test',
            task: 'task',
        ));

        self::assertTrue($result->isError);
        self::assertSame('Agent crashed', $result->errorMessage);
        self::assertSame(1, $result->exitCode);
        self::assertSame('', $result->outputText);
    }
}
