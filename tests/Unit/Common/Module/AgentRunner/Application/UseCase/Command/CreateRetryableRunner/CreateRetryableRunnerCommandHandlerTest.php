<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\AgentRunner\Application\UseCase\Command\CreateRetryableRunner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\CreateRetryableRunner\CreateRetryableRunnerCommandHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\RetryPolicyVo;

#[CoversClass(CreateRetryableRunnerCommandHandler::class)]
final class CreateRetryableRunnerCommandHandlerTest extends TestCase
{
    private AgentRunnerInterface&MockObject $runner;
    private RetryableRunnerFactoryInterface&MockObject $retryFactory;
    private CreateRetryableRunnerCommandHandler $handler;

    protected function setUp(): void
    {
        $this->runner = $this->createMock(AgentRunnerInterface::class);
        $this->retryFactory = $this->createMock(RetryableRunnerFactoryInterface::class);
        $this->handler = new CreateRetryableRunnerCommandHandler($this->retryFactory);
    }

    #[Test]
    public function handleCreatesRetryableRunner(): void
    {
        $retryRunner = $this->createMock(AgentRunnerInterface::class);

        $this->retryFactory->expects(self::once())->method('createRetryableRunner')
            ->willReturnCallback(function (
                AgentRunnerInterface $r,
                RetryPolicyVo $policy,
            ) use ($retryRunner): AgentRunnerInterface {
                self::assertSame($this->runner, $r);
                self::assertSame(3, $policy->getMaxRetries());
                self::assertSame(500, $policy->getInitialDelayMs());
                self::assertSame(10000, $policy->getMaxDelayMs());
                self::assertSame(2.0, $policy->getMultiplier());

                return $retryRunner;
            });

        $result = $this->handler->handle(
            runner: $this->runner,
            maxRetries: 3,
            initialDelayMs: 500,
            maxDelayMs: 10000,
            multiplier: 2.0,
        );

        self::assertSame($retryRunner, $result->runner);
    }

    #[Test]
    public function handleUsesDefaultRetryParams(): void
    {
        $retryRunner = $this->createMock(AgentRunnerInterface::class);

        $this->retryFactory->expects(self::once())->method('createRetryableRunner')
            ->willReturnCallback(function (
                AgentRunnerInterface $r,
                RetryPolicyVo $policy,
            ) use ($retryRunner): AgentRunnerInterface {
                self::assertSame(5, $policy->getMaxRetries());
                self::assertSame(1000, $policy->getInitialDelayMs());
                self::assertSame(30000, $policy->getMaxDelayMs());
                self::assertSame(2.0, $policy->getMultiplier());

                return $retryRunner;
            });

        $result = $this->handler->handle(
            runner: $this->runner,
            maxRetries: 5,
        );

        self::assertSame($retryRunner, $result->runner);
    }
}
