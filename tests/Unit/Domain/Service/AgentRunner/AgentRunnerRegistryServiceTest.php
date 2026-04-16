<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Tests\Unit\Domain\Service\AgentRunner;

use TasK\Orchestrator\Domain\Exception\RunnerNotFoundException;
use TasK\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TasK\Orchestrator\Domain\Service\AgentRunner\AgentRunnerRegistryService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentRunnerRegistryService::class)]
final class AgentRunnerRegistryServiceTest extends TestCase
{
    #[Test]
    public function constructBuildsMapFromIterable(): void
    {
        $runner = $this->createMock(AgentRunnerInterface::class);
        $runner->method('getName')->willReturn('pi');

        $service = new AgentRunnerRegistryService([$runner]);

        self::assertSame($runner, $service->get('pi'));
    }

    #[Test]
    public function getThrowsOnUnknownRunner(): void
    {
        $service = new AgentRunnerRegistryService([]);

        $this->expectException(RunnerNotFoundException::class);
        $this->expectExceptionMessage('codex');

        $service->get('codex');
    }

    #[Test]
    public function getDefaultReturnsFirstRunner(): void
    {
        $runner1 = $this->createMock(AgentRunnerInterface::class);
        $runner1->method('getName')->willReturn('pi');

        $service = new AgentRunnerRegistryService([$runner1]);

        self::assertSame($runner1, $service->getDefault());
    }

    #[Test]
    public function getDefaultThrowsOnEmptyRegistry(): void
    {
        $service = new AgentRunnerRegistryService([]);

        $this->expectException(RunnerNotFoundException::class);

        $service->getDefault();
    }

    #[Test]
    public function listReturnsAllRunners(): void
    {
        $runner1 = $this->createMock(AgentRunnerInterface::class);
        $runner1->method('getName')->willReturn('pi');

        $runner2 = $this->createMock(AgentRunnerInterface::class);
        $runner2->method('getName')->willReturn('codex');

        $service = new AgentRunnerRegistryService([$runner1, $runner2]);

        $list = $service->list();

        self::assertCount(2, $list);
        self::assertArrayHasKey('pi', $list);
        self::assertArrayHasKey('codex', $list);
    }
}
