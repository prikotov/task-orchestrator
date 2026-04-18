<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\UseCase\Query\GetRunners;

use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GetRunners\GetRunnersQuery;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\ResolveAgentRunnerServiceInterface;
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
        $runner = $this->createMock(RunAgentServiceInterface::class);
        $runner->method('isAvailable')->willReturn(true);

        $registry = $this->createMock(ResolveAgentRunnerServiceInterface::class);
        $registry->method('list')->willReturn(['pi' => $runner]);

        $handler = new GetRunnersQueryHandler($registry);
        $result = ($handler)(new GetRunnersQuery());

        self::assertCount(1, $result);
        self::assertSame('pi', $result[0]->name);
        self::assertTrue($result[0]->isAvailable);
    }

    #[Test]
    public function invokeReturnsEmptyListWhenNoRunners(): void
    {
        $registry = $this->createMock(ResolveAgentRunnerServiceInterface::class);
        $registry->method('list')->willReturn([]);

        $handler = new GetRunnersQueryHandler($registry);
        $result = ($handler)(new GetRunnersQuery());

        self::assertEmpty($result);
    }

    #[Test]
    public function invokeReturnsMultipleRunnersWithAvailability(): void
    {
        $piRunner = $this->createMock(RunAgentServiceInterface::class);
        $piRunner->method('isAvailable')->willReturn(true);

        $codexRunner = $this->createMock(RunAgentServiceInterface::class);
        $codexRunner->method('isAvailable')->willReturn(false);

        $registry = $this->createMock(ResolveAgentRunnerServiceInterface::class);
        $registry->method('list')->willReturn([
            'pi' => $piRunner,
            'codex' => $codexRunner,
        ]);

        $handler = new GetRunnersQueryHandler($registry);
        $result = ($handler)(new GetRunnersQuery());

        self::assertCount(2, $result);
        self::assertSame('pi', $result[0]->name);
        self::assertTrue($result[0]->isAvailable);
        self::assertSame('codex', $result[1]->name);
        self::assertFalse($result[1]->isAvailable);
    }
}
