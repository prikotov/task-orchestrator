<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Exception;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Exception\AgentException;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Exception\NotFoundExceptionInterface as AgentRunnerNotFoundExceptionInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\ChainNotFoundException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\NotFoundExceptionInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\OrchestratorException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\RoleNotFoundException;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Exception\RunnerNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunnerNotFoundException::class)]
#[CoversClass(RoleNotFoundException::class)]
#[CoversClass(ChainNotFoundException::class)]
#[CoversClass(OrchestratorException::class)]
final class ExceptionTest extends TestCase
{
    #[Test]
    public function runnerNotFoundExceptionImplementsInterface(): void
    {
        $exception = new RunnerNotFoundException('codex');

        self::assertInstanceOf(AgentRunnerNotFoundExceptionInterface::class, $exception);
        self::assertStringContainsString('codex', $exception->getMessage());
    }

    #[Test]
    public function roleNotFoundExceptionImplementsInterface(): void
    {
        $exception = new RoleNotFoundException('nonexistent_role');

        self::assertInstanceOf(NotFoundExceptionInterface::class, $exception);
        self::assertStringContainsString('nonexistent_role', $exception->getMessage());
    }

    #[Test]
    public function chainNotFoundExceptionImplementsInterface(): void
    {
        $exception = new ChainNotFoundException('nonexistent_chain');

        self::assertInstanceOf(NotFoundExceptionInterface::class, $exception);
        self::assertStringContainsString('nonexistent_chain', $exception->getMessage());
        self::assertStringContainsString('Chain', $exception->getMessage());
    }

    #[Test]
    public function runnerNotFoundExceptionExtendsAgentException(): void
    {
        self::assertInstanceOf(AgentException::class, new RunnerNotFoundException('test'));
    }

    #[Test]
    public function orchestratorExceptionsExtendOrchestratorException(): void
    {
        self::assertInstanceOf(OrchestratorException::class, new RoleNotFoundException('test'));
        self::assertInstanceOf(OrchestratorException::class, new ChainNotFoundException('test'));
    }

    #[Test]
    public function exceptionsSupportPrevious(): void
    {
        $previous = new \RuntimeException('root cause');
        $exception = new RunnerNotFoundException('test', $previous);

        self::assertSame($previous, $exception->getPrevious());
    }
}
