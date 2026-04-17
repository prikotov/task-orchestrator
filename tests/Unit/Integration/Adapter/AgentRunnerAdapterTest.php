<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Integration\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommand;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Integration\Adapter\AgentDtoMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Integration\Adapter\AgentRunnerAdapter;

#[CoversClass(AgentRunnerAdapter::class)]
final class AgentRunnerAdapterTest extends TestCase
{
    private RunAgentCommandHandler&MockObject $runAgentHandler;
    private AgentDtoMapper $mapper;
    private ChainRunRequestVo $request;

    protected function setUp(): void
    {
        $this->runAgentHandler = $this->createMock(RunAgentCommandHandler::class);
        $this->mapper = new AgentDtoMapper();
        $this->request = new ChainRunRequestVo(role: 'dev', task: 'Write code');
    }

    #[Test]
    public function runDelegatesToRunAgentHandler(): void
    {
        $resultDto = new RunAgentResultDto(
            outputText: 'Code written',
            inputTokens: 100,
            outputTokens: 50,
            cacheReadTokens: 0,
            cacheWriteTokens: 0,
            cost: 0.01,
            exitCode: 0,
            model: 'gpt-4',
            turns: 1,
            isError: false,
            errorMessage: null,
        );

        $this->runAgentHandler->expects(self::once())->method('handle')
            ->willReturnCallback(function (RunAgentCommand $cmd, string $runnerName) use ($resultDto): RunAgentResultDto {
                self::assertSame('dev', $cmd->role);
                self::assertSame('Write code', $cmd->task);
                self::assertSame('pi', $runnerName);

                return $resultDto;
            });

        $adapter = new AgentRunnerAdapter($this->runAgentHandler, $this->mapper, 'pi', true);
        $result = $adapter->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('Code written', $result->getOutputText());
    }

    #[Test]
    public function runWithRetryPolicyPassesRetryParams(): void
    {
        $retryPolicy = new ChainRetryPolicyVo(maxRetries: 3);

        $this->runAgentHandler->expects(self::once())->method('handle')
            ->willReturnCallback(function (RunAgentCommand $cmd): RunAgentResultDto {
                self::assertSame(3, $cmd->retryMaxRetries);
                self::assertSame(1000, $cmd->retryInitialDelayMs);

                return new RunAgentResultDto(
                    outputText: 'Retried OK',
                    inputTokens: 0,
                    outputTokens: 0,
                    cacheReadTokens: 0,
                    cacheWriteTokens: 0,
                    cost: 0.0,
                    exitCode: 0,
                    model: null,
                    turns: 0,
                    isError: false,
                    errorMessage: null,
                );
            });

        $adapter = new AgentRunnerAdapter($this->runAgentHandler, $this->mapper, 'pi', true);
        $result = $adapter->run($this->request, $retryPolicy);

        self::assertFalse($result->isError());
        self::assertSame('Retried OK', $result->getOutputText());
    }

    #[Test]
    public function runMapsErrorResult(): void
    {
        $resultDto = new RunAgentResultDto(
            outputText: '',
            inputTokens: 0,
            outputTokens: 0,
            cacheReadTokens: 0,
            cacheWriteTokens: 0,
            cost: 0.0,
            exitCode: 124,
            model: null,
            turns: 0,
            isError: true,
            errorMessage: 'Timeout',
        );

        $this->runAgentHandler->method('handle')->willReturn($resultDto);

        $adapter = new AgentRunnerAdapter($this->runAgentHandler, $this->mapper, 'pi', true);
        $result = $adapter->run($this->request);

        self::assertTrue($result->isError());
        self::assertSame('Timeout', $result->getErrorMessage());
        self::assertSame(124, $result->getExitCode());
    }

    #[Test]
    public function getNameReturnsRunnerName(): void
    {
        $adapter = new AgentRunnerAdapter($this->runAgentHandler, $this->mapper, 'pi', true);

        self::assertSame('pi', $adapter->getName());
    }

    #[Test]
    public function isAvailableReturnsTrueWhenRunnerAvailable(): void
    {
        $adapter = new AgentRunnerAdapter($this->runAgentHandler, $this->mapper, 'pi', true);

        self::assertTrue($adapter->isAvailable());
    }

    #[Test]
    public function isAvailableReturnsFalseWhenRunnerUnavailable(): void
    {
        $adapter = new AgentRunnerAdapter($this->runAgentHandler, $this->mapper, 'pi', false);

        self::assertFalse($adapter->isAvailable());
    }
}
