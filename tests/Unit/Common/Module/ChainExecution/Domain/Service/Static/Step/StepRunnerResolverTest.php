<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Domain\Service\Static\Step;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\AgentStepRunner;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\QualityGateStepRunner;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\StepRunnerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\StepRunnerResolver;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\ToolStepRunnerStrategy;

#[CoversClass(StepRunnerResolver::class)]
final class StepRunnerResolverTest extends TestCase
{
    #[Test]
    public function resolveReturnsAgentRunner(): void
    {
        $agentRunner = $this->createMock(StepRunnerInterface::class);
        $agentRunner->method('supports')->willReturnCallback(
            static fn(ChainStepTypeEnum $type): bool => $type === ChainStepTypeEnum::agent,
        );

        $resolver = new StepRunnerResolver([$agentRunner]);

        $result = $resolver->resolve(ChainStepTypeEnum::agent);
        self::assertSame($agentRunner, $result);
    }

    #[Test]
    public function resolveReturnsCorrectRunnerForType(): void
    {
        $agentRunner = $this->createMock(StepRunnerInterface::class);
        $agentRunner->method('supports')->willReturnCallback(
            static fn(ChainStepTypeEnum $type): bool => $type === ChainStepTypeEnum::agent,
        );

        $gateRunner = $this->createMock(StepRunnerInterface::class);
        $gateRunner->method('supports')->willReturnCallback(
            static fn(ChainStepTypeEnum $type): bool => $type === ChainStepTypeEnum::qualityGate,
        );

        $toolRunner = $this->createMock(StepRunnerInterface::class);
        $toolRunner->method('supports')->willReturnCallback(
            static fn(ChainStepTypeEnum $type): bool => $type === ChainStepTypeEnum::tool,
        );

        $resolver = new StepRunnerResolver([$agentRunner, $gateRunner, $toolRunner]);

        self::assertSame($agentRunner, $resolver->resolve(ChainStepTypeEnum::agent));
        self::assertSame($gateRunner, $resolver->resolve(ChainStepTypeEnum::qualityGate));
        self::assertSame($toolRunner, $resolver->resolve(ChainStepTypeEnum::tool));
    }

    #[Test]
    public function resolveThrowsWhenNoRunnerFound(): void
    {
        $resolver = new StepRunnerResolver([]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No StepRunnerInterface found for step type "agent".');

        $resolver->resolve(ChainStepTypeEnum::agent);
    }

    #[Test]
    public function resolveWithEmptyIterableThrows(): void
    {
        $resolver = new StepRunnerResolver(new \ArrayIterator());

        $this->expectException(\LogicException::class);

        $resolver->resolve(ChainStepTypeEnum::qualityGate);
    }
}
