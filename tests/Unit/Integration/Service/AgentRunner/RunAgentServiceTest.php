<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Integration\Service\AgentRunner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\AgentRunner\RunAgentService;

#[CoversClass(RunAgentService::class)]
final class RunAgentServiceTest extends TestCase
{
    private ChainRunRequestVo $request;

    protected function setUp(): void
    {
        $this->request = new ChainRunRequestVo(role: 'dev', task: 'Write code', runnerName: 'pi');
    }

    #[Test]
    public function runDelegatesToInnerService(): void
    {
        $expectedResult = ChainRunResultVo::createFromSuccess(
            outputText: 'Code written',
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.01,
        );

        $inner = $this->createMock(RunAgentServiceInterface::class);
        $inner->expects($this->once())
            ->method('run')
            ->with($this->request, null)
            ->willReturn($expectedResult);

        $service = new RunAgentService($inner);
        $result = $service->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('Code written', $result->getOutputText());
        self::assertSame(100, $result->getInputTokens());
    }

    #[Test]
    public function runPassesRetryPolicyToInnerService(): void
    {
        $retryPolicy = new ExecutionRetryPolicyVo(maxRetries: 3);
        $expectedResult = ChainRunResultVo::createFromSuccess(outputText: 'Retried OK');

        $inner = $this->createMock(RunAgentServiceInterface::class);
        $inner->expects($this->once())
            ->method('run')
            ->with($this->request, $retryPolicy)
            ->willReturn($expectedResult);

        $service = new RunAgentService($inner);
        $result = $service->run($this->request, $retryPolicy);

        self::assertFalse($result->isError());
        self::assertSame('Retried OK', $result->getOutputText());
    }

    #[Test]
    public function runReturnsErrorResultFromInnerService(): void
    {
        $expectedResult = ChainRunResultVo::createFromError(
            errorMessage: 'Timeout',
            timedOut: true,
        );

        $inner = $this->createMock(RunAgentServiceInterface::class);
        $inner->method('run')->willReturn($expectedResult);

        $service = new RunAgentService($inner);
        $result = $service->run($this->request);

        self::assertTrue($result->isError());
        self::assertSame('Timeout', $result->getErrorMessage());
        self::assertTrue($result->isTimedOut());
    }
}
