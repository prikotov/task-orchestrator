<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\AgentRunner\Application\UseCase\Query\GetRunnerByName;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunnerByName\GetRunnerByNameQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunnerByName\GetRunnerByNameQueryHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Exception\RunnerNotFoundException;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;

#[CoversClass(GetRunnerByNameQueryHandler::class)]
final class GetRunnerByNameQueryHandlerTest extends TestCase
{
    private AgentRunnerRegistryServiceInterface&MockObject $registry;
    private GetRunnerByNameQueryHandler $handler;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $this->handler = new GetRunnerByNameQueryHandler($this->registry);
    }

    #[Test]
    public function handleReturnsRunnerDtoWhenFoundByName(): void
    {
        $runner = $this->createMock(AgentRunnerInterface::class);
        $runner->method('getName')->willReturn('pi');
        $runner->method('isAvailable')->willReturn(true);

        $this->registry->method('get')->with('pi')->willReturn($runner);

        $result = $this->handler->handle(new GetRunnerByNameQuery(name: 'pi'));

        self::assertNotNull($result);
        self::assertSame('pi', $result->name);
        self::assertTrue($result->isAvailable);
    }

    #[Test]
    public function handleReturnsNullWhenRunnerNotFound(): void
    {
        $this->registry->method('get')->with('unknown')
            ->willThrowException(new RunnerNotFoundException('unknown'));

        $result = $this->handler->handle(new GetRunnerByNameQuery(name: 'unknown'));

        self::assertNull($result);
    }

    #[Test]
    public function handleReturnsDefaultRunnerWhenNameIsNull(): void
    {
        $runner = $this->createMock(AgentRunnerInterface::class);
        $runner->method('getName')->willReturn('pi');
        $runner->method('isAvailable')->willReturn(true);

        $this->registry->method('getDefault')->willReturn($runner);

        $result = $this->handler->handle(new GetRunnerByNameQuery(name: null));

        self::assertNotNull($result);
        self::assertSame('pi', $result->name);
        self::assertTrue($result->isAvailable);
    }

    #[Test]
    public function handleReturnsNullWhenDefaultRunnerNotFound(): void
    {
        $this->registry->method('getDefault')
            ->willThrowException(new RunnerNotFoundException('default'));

        $result = $this->handler->handle(new GetRunnerByNameQuery(name: null));

        self::assertNull($result);
    }

    #[Test]
    public function invokeDelegatesToHandle(): void
    {
        $runner = $this->createMock(AgentRunnerInterface::class);
        $runner->method('getName')->willReturn('pi');
        $runner->method('isAvailable')->willReturn(false);

        $this->registry->method('get')->with('pi')->willReturn($runner);

        $result = ($this->handler)(new GetRunnerByNameQuery(name: 'pi'));

        self::assertNotNull($result);
        self::assertSame('pi', $result->name);
        self::assertFalse($result->isAvailable);
    }
}
