<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Domain\Service\Static\Step;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\ExecuteAgentStepService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\ExecuteQualityGateStepService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\ExecuteStepServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\ResolveStepRunnerService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\ExecuteToolStepService;

#[CoversClass(ResolveStepRunnerService::class)]
final class ResolveStepRunnerServiceTest extends TestCase
{
    #[Test]
    public function resolveReturnsAgentRunner(): void
    {
        $agentRunner = $this->createMock(ExecuteStepServiceInterface::class);
        $agentRunner->method('supports')->willReturnCallback(
            static fn(ChainStepTypeEnum $type): bool => $type === ChainStepTypeEnum::agent,
        );

        $resolver = new ResolveStepRunnerService([$agentRunner]);

        $result = $resolver->resolve(ChainStepTypeEnum::agent);
        self::assertSame($agentRunner, $result);
    }

    #[Test]
    public function resolveReturnsCorrectRunnerForType(): void
    {
        $agentRunner = $this->createMock(ExecuteStepServiceInterface::class);
        $agentRunner->method('supports')->willReturnCallback(
            static fn(ChainStepTypeEnum $type): bool => $type === ChainStepTypeEnum::agent,
        );

        $gateRunner = $this->createMock(ExecuteStepServiceInterface::class);
        $gateRunner->method('supports')->willReturnCallback(
            static fn(ChainStepTypeEnum $type): bool => $type === ChainStepTypeEnum::qualityGate,
        );

        $toolRunner = $this->createMock(ExecuteStepServiceInterface::class);
        $toolRunner->method('supports')->willReturnCallback(
            static fn(ChainStepTypeEnum $type): bool => $type === ChainStepTypeEnum::tool,
        );

        $resolver = new ResolveStepRunnerService([$agentRunner, $gateRunner, $toolRunner]);

        self::assertSame($agentRunner, $resolver->resolve(ChainStepTypeEnum::agent));
        self::assertSame($gateRunner, $resolver->resolve(ChainStepTypeEnum::qualityGate));
        self::assertSame($toolRunner, $resolver->resolve(ChainStepTypeEnum::tool));
    }

    #[Test]
    public function resolveThrowsWhenNoRunnerFound(): void
    {
        $resolver = new ResolveStepRunnerService([]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No ExecuteStepServiceInterface found for step type "agent".');

        $resolver->resolve(ChainStepTypeEnum::agent);
    }

    #[Test]
    public function resolveWithEmptyIterableThrows(): void
    {
        $resolver = new ResolveStepRunnerService(new \ArrayIterator());

        $this->expectException(\LogicException::class);

        $resolver->resolve(ChainStepTypeEnum::qualityGate);
    }
}
