<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\RetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Adapter\AgentRunnerAdapter;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Adapter\AgentVoMapper;

#[CoversClass(AgentRunnerAdapter::class)]
final class AgentRunnerAdapterTest extends TestCase
{
    private AgentRunnerInterface&MockObject $runner;
    private RetryableRunnerFactoryInterface&MockObject $retryFactory;
    private AgentVoMapper $mapper;
    private ChainRunRequestVo $request;

    protected function setUp(): void
    {
        $this->runner = $this->createMock(AgentRunnerInterface::class);
        $this->retryFactory = $this->createMock(RetryableRunnerFactoryInterface::class);
        $this->mapper = new AgentVoMapper();
        $this->request = new ChainRunRequestVo(role: 'dev', task: 'Write code');
    }

    #[Test]
    public function runDelegatesToRunnerWithoutRetry(): void
    {
        $agentResult = AgentResultVo::createFromSuccess(outputText: 'Code written');

        $this->mapper->mapToAgentRequest($this->request);
        $this->retryFactory->expects(self::never())->method('createRetryableRunner');
        $this->runner->expects(self::once())->method('run')->willReturnCallback(
            function (AgentRunRequestVo $req): AgentResultVo {
                self::assertSame('dev', $req->getRole());
                self::assertSame('Write code', $req->getTask());

                return AgentResultVo::createFromSuccess(outputText: 'Code written');
            },
        );

        $adapter = new AgentRunnerAdapter($this->runner, $this->retryFactory, $this->mapper);
        $result = $adapter->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('Code written', $result->getOutputText());
    }

    #[Test]
    public function runWrapsRunnerWithRetryWhenPolicyProvided(): void
    {
        $retryPolicy = new ChainRetryPolicyVo(maxRetries: 3);
        $retryRunner = $this->createMock(AgentRunnerInterface::class);

        $this->retryFactory->expects(self::once())->method('createRetryableRunner')
            ->willReturnCallback(function (AgentRunnerInterface $r, RetryPolicyVo $p) use ($retryRunner): AgentRunnerInterface {
                self::assertSame($this->runner, $r);
                self::assertSame(3, $p->getMaxRetries());

                return $retryRunner;
            });
        $retryRunner->expects(self::once())->method('run')->willReturnCallback(
            function (AgentRunRequestVo $req): AgentResultVo {
                self::assertSame('dev', $req->getRole());

                return AgentResultVo::createFromSuccess(outputText: 'Retried OK');
            },
        );

        $adapter = new AgentRunnerAdapter($this->runner, $this->retryFactory, $this->mapper);
        $result = $adapter->run($this->request, $retryPolicy);

        self::assertFalse($result->isError());
        self::assertSame('Retried OK', $result->getOutputText());
    }

    #[Test]
    public function runMapsErrorResult(): void
    {
        $agentResult = AgentResultVo::createFromError(errorMessage: 'Timeout', exitCode: 124);

        $this->runner->expects(self::once())->method('run')->willReturn($agentResult);

        $adapter = new AgentRunnerAdapter($this->runner, $this->retryFactory, $this->mapper);
        $result = $adapter->run($this->request);

        self::assertTrue($result->isError());
        self::assertSame('Timeout', $result->getErrorMessage());
        self::assertSame(124, $result->getExitCode());
    }

    #[Test]
    public function getNameDelegatesToRunner(): void
    {
        $this->runner->method('getName')->willReturn('pi');

        $adapter = new AgentRunnerAdapter($this->runner, $this->retryFactory, $this->mapper);

        self::assertSame('pi', $adapter->getName());
    }

    #[Test]
    public function isAvailableDelegatesToRunner(): void
    {
        $this->runner->method('isAvailable')->willReturn(true);

        $adapter = new AgentRunnerAdapter($this->runner, $this->retryFactory, $this->mapper);

        self::assertTrue($adapter->isAvailable());
    }

    #[Test]
    public function isAvailableReturnsFalseWhenRunnerUnavailable(): void
    {
        $this->runner->method('isAvailable')->willReturn(false);

        $adapter = new AgentRunnerAdapter($this->runner, $this->retryFactory, $this->mapper);

        self::assertFalse($adapter->isAvailable());
    }
}
