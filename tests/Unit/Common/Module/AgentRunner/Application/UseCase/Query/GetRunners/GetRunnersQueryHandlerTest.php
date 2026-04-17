<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;

#[CoversClass(GetRunnersQueryHandler::class)]
final class GetRunnersQueryHandlerTest extends TestCase
{
    private AgentRunnerRegistryServiceInterface&MockObject $registry;
    private GetRunnersQueryHandler $handler;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $this->handler = new GetRunnersQueryHandler($this->registry);
    }

    #[Test]
    public function handleReturnsAllRunners(): void
    {
        $piRunner = $this->createMock(AgentRunnerInterface::class);
        $piRunner->method('getName')->willReturn('pi');
        $piRunner->method('isAvailable')->willReturn(true);

        $codexRunner = $this->createMock(AgentRunnerInterface::class);
        $codexRunner->method('getName')->willReturn('codex');
        $codexRunner->method('isAvailable')->willReturn(false);

        $this->registry->method('list')->willReturn([
            'pi' => $piRunner,
            'codex' => $codexRunner,
        ]);

        $result = $this->handler->handle(new GetRunnersQuery());

        self::assertCount(2, $result->runners);
        self::assertSame('pi', $result->runners[0]->name);
        self::assertTrue($result->runners[0]->isAvailable);
        self::assertSame('codex', $result->runners[1]->name);
        self::assertFalse($result->runners[1]->isAvailable);
    }

    #[Test]
    public function handleFiltersByName(): void
    {
        $piRunner = $this->createMock(AgentRunnerInterface::class);
        $piRunner->method('getName')->willReturn('pi');
        $piRunner->method('isAvailable')->willReturn(true);

        $codexRunner = $this->createMock(AgentRunnerInterface::class);
        $codexRunner->method('getName')->willReturn('codex');
        $codexRunner->method('isAvailable')->willReturn(true);

        $this->registry->method('list')->willReturn([
            'pi' => $piRunner,
            'codex' => $codexRunner,
        ]);

        $result = $this->handler->handle(new GetRunnersQuery(filterName: 'pi'));

        self::assertCount(1, $result->runners);
        self::assertSame('pi', $result->runners[0]->name);
    }

    #[Test]
    public function handleReturnsEmptyWhenNoRunners(): void
    {
        $this->registry->method('list')->willReturn([]);

        $result = $this->handler->handle(new GetRunnersQuery());

        self::assertEmpty($result->runners);
    }

    #[Test]
    public function handleFilterReturnsEmptyWhenNameNotFound(): void
    {
        $piRunner = $this->createMock(AgentRunnerInterface::class);
        $piRunner->method('getName')->willReturn('pi');
        $piRunner->method('isAvailable')->willReturn(true);

        $this->registry->method('list')->willReturn(['pi' => $piRunner]);

        $result = $this->handler->handle(new GetRunnersQuery(filterName: 'nonexistent'));

        self::assertEmpty($result->runners);
    }
}
