<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Infrastructure\Orchestrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ExecutionStrategyInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Security\CheckChainSecurityServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\SecurityPolicyViolationException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Orchestrator\SecurityPolicyExecutionStrategyDecorator;

#[CoversClass(SecurityPolicyExecutionStrategyDecorator::class)]
final class SecurityPolicyExecutionStrategyDecoratorTest extends TestCase
{
    private ExecutionStrategyInterface $decoratedStrategy;
    private CheckChainSecurityServiceInterface $chainSecurityPolicy;
    private SecurityPolicyExecutionStrategyDecorator $decorator;

    protected function setUp(): void
    {
        $this->decoratedStrategy = $this->createMock(ExecutionStrategyInterface::class);
        $this->chainSecurityPolicy = $this->createMock(CheckChainSecurityServiceInterface::class);
        $this->decorator = new SecurityPolicyExecutionStrategyDecorator(
            decoratedStrategy: $this->decoratedStrategy,
            chainSecurityPolicy: $this->chainSecurityPolicy,
        );
    }

    private function createStaticChain(string $name = 'code-review'): ChainDefinitionVo
    {
        return ChainDefinitionVo::createFromSteps(
            name: $name,
            description: 'Test chain',
            steps: [
                new \TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo(
                    type: \TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainStepTypeEnum::agent,
                    role: 'reviewer',
                    runner: 'pi',
                ),
            ],
        );
    }

    private function createCommand(string $chainName = 'code-review'): OrchestrateChainCommand
    {
        return new OrchestrateChainCommand(chainName: $chainName, task: 'test task');
    }

    // ─── execute ───────────────────────────────────────────────────────

    #[Test]
    public function executeChecksChainSecurityBeforeDelegation(): void
    {
        $chain = $this->createStaticChain();
        $command = $this->createCommand();
        $expectedResult = new OrchestrateChainResultDto();

        // Security check is called FIRST
        $this->chainSecurityPolicy
            ->expects($this->once())
            ->method('checkChainExecution')
            ->with('code-review', ChainTypeEnum::staticType);

        // Then decorated strategy is called
        $this->decoratedStrategy
            ->expects($this->once())
            ->method('execute')
            ->with($chain, $command)
            ->willReturn($expectedResult);

        $result = $this->decorator->execute($chain, $command);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function executeDoesNotCallStrategyOnViolation(): void
    {
        $chain = $this->createStaticChain('blocked-chain');
        $command = $this->createCommand('blocked-chain');

        // Security check throws violation
        $this->chainSecurityPolicy
            ->method('checkChainExecution')
            ->willThrowException(new SecurityPolicyViolationException(
                'blocked-chain',
                'Chain is not allowed.',
            ));

        // Decorated strategy MUST NOT be called
        $this->decoratedStrategy
            ->expects($this->never())
            ->method('execute');

        $this->expectException(SecurityPolicyViolationException::class);

        $this->decorator->execute($chain, $command);
    }

    // ─── resume ────────────────────────────────────────────────────────

    #[Test]
    public function resumeChecksChainSecurityBeforeDelegation(): void
    {
        $chain = $this->createStaticChain();
        $command = $this->createCommand();
        $expectedResult = new OrchestrateChainResultDto();

        // Security check is called FIRST
        $this->chainSecurityPolicy
            ->expects($this->once())
            ->method('checkChainExecution')
            ->with('code-review', ChainTypeEnum::staticType);

        // Then decorated strategy is called
        $this->decoratedStrategy
            ->expects($this->once())
            ->method('resume')
            ->with($chain, $command)
            ->willReturn($expectedResult);

        $result = $this->decorator->resume($chain, $command);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function resumeDoesNotCallStrategyOnViolation(): void
    {
        $chain = $this->createStaticChain('blocked-chain');
        $command = $this->createCommand('blocked-chain');

        // Security check throws violation
        $this->chainSecurityPolicy
            ->method('checkChainExecution')
            ->willThrowException(new SecurityPolicyViolationException(
                'blocked-chain',
                'Chain is not allowed.',
            ));

        // Decorated strategy MUST NOT be called
        $this->decoratedStrategy
            ->expects($this->never())
            ->method('resume');

        $this->expectException(SecurityPolicyViolationException::class);

        $this->decorator->resume($chain, $command);
    }

    // ─── supports ──────────────────────────────────────────────────────

    #[Test]
    public function supportsDelegatesToDecoratedStrategy(): void
    {
        $chain = $this->createStaticChain();

        $this->decoratedStrategy
            ->expects($this->once())
            ->method('supports')
            ->with($chain)
            ->willReturn(true);

        $this->assertTrue($this->decorator->supports($chain));
    }

    #[Test]
    public function supportsReturnsFalseWhenInnerDoesNotSupport(): void
    {
        $chain = $this->createStaticChain();

        $this->decoratedStrategy
            ->method('supports')
            ->willReturn(false);

        $this->assertFalse($this->decorator->supports($chain));
    }

    #[Test]
    public function supportsDoesNotCheckSecurityPolicy(): void
    {
        $chain = $this->createStaticChain();

        // Security check MUST NOT be called during supports()
        $this->chainSecurityPolicy
            ->expects($this->never())
            ->method('checkChainExecution');

        $this->decoratedStrategy
            ->method('supports')
            ->willReturn(true);

        $this->decorator->supports($chain);
    }
}
