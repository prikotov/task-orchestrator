<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Integration\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommand;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentResultDto;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Integration\Adapter\AgentDtoMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Integration\Adapter\AgentRunnerAdapter;

#[CoversClass(AgentRunnerAdapter::class)]
final class AgentRunnerAdapterTest extends TestCase
{
    private ChainRunRequestVo $request;

    protected function setUp(): void
    {
        $this->request = new ChainRunRequestVo(role: 'dev', task: 'Write code');
    }

    #[Test]
    public function runDelegatesToRunAgentHandler(): void
    {
        $runner = $this->createMock(AgentRunnerInterface::class);
        $runner->method('run')->willReturn(AgentResultVo::createFromSuccess(
            outputText: 'Code written',
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.01,
            model: 'gpt-4',
            turns: 1,
        ));

        $registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $registry->method('get')->with('pi')->willReturn($runner);

        $retryFactory = $this->createMock(RetryableRunnerFactoryInterface::class);
        $handler = new RunAgentCommandHandler($registry, $retryFactory);
        $mapper = new AgentDtoMapper();

        $adapter = new AgentRunnerAdapter($handler, $mapper, 'pi', true);
        $result = $adapter->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('Code written', $result->getOutputText());
        self::assertSame(100, $result->getInputTokens());
    }

    #[Test]
    public function runWithRetryPolicyPassesRetryParams(): void
    {
        $retryPolicy = new ChainRetryPolicyVo(maxRetries: 3);

        $retryRunner = $this->createMock(AgentRunnerInterface::class);
        $retryRunner->method('run')->willReturn(AgentResultVo::createFromSuccess(outputText: 'Retried OK'));

        $runner = $this->createMock(AgentRunnerInterface::class);

        $registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $registry->method('get')->with('pi')->willReturn($runner);

        $retryFactory = $this->createMock(RetryableRunnerFactoryInterface::class);
        $retryFactory->method('createRetryableRunner')->willReturn($retryRunner);

        $handler = new RunAgentCommandHandler($registry, $retryFactory);
        $mapper = new AgentDtoMapper();

        $adapter = new AgentRunnerAdapter($handler, $mapper, 'pi', true);
        $result = $adapter->run($this->request, $retryPolicy);

        self::assertFalse($result->isError());
        self::assertSame('Retried OK', $result->getOutputText());
    }

    #[Test]
    public function runMapsErrorResult(): void
    {
        $runner = $this->createMock(AgentRunnerInterface::class);
        $runner->method('run')->willReturn(AgentResultVo::createFromError(
            errorMessage: 'Timeout',
            exitCode: 124,
        ));

        $registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $registry->method('get')->with('pi')->willReturn($runner);

        $retryFactory = $this->createMock(RetryableRunnerFactoryInterface::class);
        $handler = new RunAgentCommandHandler($registry, $retryFactory);
        $mapper = new AgentDtoMapper();

        $adapter = new AgentRunnerAdapter($handler, $mapper, 'pi', true);
        $result = $adapter->run($this->request);

        self::assertTrue($result->isError());
        self::assertSame('Timeout', $result->getErrorMessage());
        self::assertSame(124, $result->getExitCode());
    }

    #[Test]
    public function getNameReturnsRunnerName(): void
    {
        $registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $retryFactory = $this->createMock(RetryableRunnerFactoryInterface::class);
        $handler = new RunAgentCommandHandler($registry, $retryFactory);
        $mapper = new AgentDtoMapper();

        $adapter = new AgentRunnerAdapter($handler, $mapper, 'pi', true);

        self::assertSame('pi', $adapter->getName());
    }

    #[Test]
    public function isAvailableReturnsTrueWhenRunnerAvailable(): void
    {
        $registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $retryFactory = $this->createMock(RetryableRunnerFactoryInterface::class);
        $handler = new RunAgentCommandHandler($registry, $retryFactory);
        $mapper = new AgentDtoMapper();

        $adapter = new AgentRunnerAdapter($handler, $mapper, 'pi', true);

        self::assertTrue($adapter->isAvailable());
    }

    #[Test]
    public function isAvailableReturnsFalseWhenRunnerUnavailable(): void
    {
        $registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $retryFactory = $this->createMock(RetryableRunnerFactoryInterface::class);
        $handler = new RunAgentCommandHandler($registry, $retryFactory);
        $mapper = new AgentDtoMapper();

        $adapter = new AgentRunnerAdapter($handler, $mapper, 'pi', false);

        self::assertFalse($adapter->isAvailable());
    }
}
