<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\UseCase\Query\GetRunners;

use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQuery as AgentRunnerGetRunnersQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler as AgentRunnerGetRunnersQueryHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GetRunners\GetRunnersQuery;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetRunnersQueryHandler::class)]
#[CoversClass(GetRunnersQuery::class)]
final class GetRunnersQueryHandlerTest extends TestCase
{
    #[Test]
    public function invokeReturnsRunnerDtos(): void
    {
        $piRunner = $this->createMock(AgentRunnerInterface::class);
        $piRunner->method('getName')->willReturn('pi');
        $piRunner->method('isAvailable')->willReturn(true);

        $registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $registry->method('list')->willReturn(['pi' => $piRunner]);

        $agentRunnerHandler = new AgentRunnerGetRunnersQueryHandler($registry);
        $handler = new GetRunnersQueryHandler($agentRunnerHandler);
        $result = ($handler)(new GetRunnersQuery());

        self::assertCount(1, $result);
        self::assertSame('pi', $result[0]->name);
        self::assertTrue($result[0]->isAvailable);
    }

    #[Test]
    public function invokeReturnsEmptyListWhenNoRunners(): void
    {
        $registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $registry->method('list')->willReturn([]);

        $agentRunnerHandler = new AgentRunnerGetRunnersQueryHandler($registry);
        $handler = new GetRunnersQueryHandler($agentRunnerHandler);
        $result = ($handler)(new GetRunnersQuery());

        self::assertEmpty($result);
    }

    #[Test]
    public function invokeReturnsMultipleRunnersWithAvailability(): void
    {
        $piRunner = $this->createMock(AgentRunnerInterface::class);
        $piRunner->method('getName')->willReturn('pi');
        $piRunner->method('isAvailable')->willReturn(true);

        $codexRunner = $this->createMock(AgentRunnerInterface::class);
        $codexRunner->method('getName')->willReturn('codex');
        $codexRunner->method('isAvailable')->willReturn(false);

        $registry = $this->createMock(AgentRunnerRegistryServiceInterface::class);
        $registry->method('list')->willReturn([
            'pi' => $piRunner,
            'codex' => $codexRunner,
        ]);

        $agentRunnerHandler = new AgentRunnerGetRunnersQueryHandler($registry);
        $handler = new GetRunnersQueryHandler($agentRunnerHandler);
        $result = ($handler)(new GetRunnersQuery());

        self::assertCount(2, $result);
        self::assertSame('pi', $result[0]->name);
        self::assertTrue($result[0]->isAvailable);
        self::assertSame('codex', $result[1]->name);
        self::assertFalse($result[1]->isAvailable);
    }
}
