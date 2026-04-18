<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Integration\Service\AgentRunner;

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
use TaskOrchestrator\Common\Module\Orchestrator\Integration\Service\AgentRunner\AgentDtoMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Integration\Service\AgentRunner\RunAgentService;

#[CoversClass(RunAgentService::class)]
final class RunAgentServiceTest extends TestCase
{
    private ChainRunRequestVo $request;

    protected function setUp(): void
    {
        $this->request = new ChainRunRequestVo(role: 'dev', task: 'Write code', runnerName: 'pi');
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

        $service = new RunAgentService($handler, $mapper);
        $result = $service->run($this->request);

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

        $service = new RunAgentService($handler, $mapper);
        $result = $service->run($this->request, $retryPolicy);

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

        $service = new RunAgentService($handler, $mapper);
        $result = $service->run($this->request);

        self::assertTrue($result->isError());
        self::assertSame('Timeout', $result->getErrorMessage());
        self::assertSame(124, $result->getExitCode());
    }

    #[Test]
    public function runUsesRunnerNameFromVo(): void
    {
        $runner = $this->createMock(AgentRunnerInterface::class);
        $runner->method('run')->willReturn(AgentResultVo::createFromSuccess(outputText: 'OK'));

        $capturedRunnerName = null;
        $registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $registry->method('get')
            ->willReturnCallback(function (string $name) use ($runner, &$capturedRunnerName): AgentRunnerInterface {
                $capturedRunnerName = $name;

                return $runner;
            });

        $retryFactory = $this->createMock(RetryableRunnerFactoryInterface::class);
        $handler = new RunAgentCommandHandler($registry, $retryFactory);
        $mapper = new AgentDtoMapper();

        $request = new ChainRunRequestVo(role: 'dev', task: 'Test', runnerName: 'codex');
        $service = new RunAgentService($handler, $mapper);
        $service->run($request);

        self::assertSame('codex', $capturedRunnerName);
    }
}
