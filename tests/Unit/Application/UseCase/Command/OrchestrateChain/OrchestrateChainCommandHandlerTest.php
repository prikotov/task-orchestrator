<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\UseCase\Command\OrchestrateChain;

use TaskOrchestrator\Common\Module\ChainExecution\Application\Contract\Chain\ExecutionStrategyInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\StaticExecutionStrategy;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Integration\ChainDefinitionProviderInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Application\Service\DynamicExecutionStrategy;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrchestrateChainCommandHandler::class)]
#[CoversClass(OrchestrateChainCommand::class)]
#[CoversClass(StaticExecutionStrategy::class)]
#[CoversClass(DynamicExecutionStrategy::class)]
final class OrchestrateChainCommandHandlerTest extends TestCase
{
    private ChainDefinitionProviderInterface $chainProvider;
    private ExecutionStrategyInterface $staticStrategy;
    private ExecutionStrategyInterface $dynamicStrategy;
    private OrchestrateChainCommandHandler $handler;

    protected function setUp(): void
    {
        $this->chainProvider = $this->createMock(ChainDefinitionProviderInterface::class);
        $this->staticStrategy = $this->createMock(ExecutionStrategyInterface::class);
        $this->dynamicStrategy = $this->createMock(ExecutionStrategyInterface::class);

        // Default supports(): static supports static, dynamic supports dynamic
        $this->staticStrategy->method('supports')
            ->willReturnCallback(static fn(ChainDefinitionInterface $chain): bool => !$chain->getSharedDefinition()->isDynamic());
        $this->dynamicStrategy->method('supports')
            ->willReturnCallback(static fn(ChainDefinitionInterface $chain): bool => $chain->getSharedDefinition()->isDynamic());

        $this->handler = $this->createHandler();
    }

    private function createHandler(): OrchestrateChainCommandHandler
    {
        return new OrchestrateChainCommandHandler(
            $this->chainProvider,
            [$this->staticStrategy, $this->dynamicStrategy],
        );
    }

    // --- Dispatcher tests ---

    #[Test]
    public function invokeDelegatesStaticChainToStaticStrategy(): void
    {
        $chain = StaticChainDefinitionVo::create(
            name: 'test',
            description: 'Test chain',
            steps: [
                ChainStepVo::agent(role: 'system_analyst', runner: 'pi'),
            ],
        );

        $this->chainProvider->method('loadChainDefinition')->with('test')->willReturn($chain);

        $staticResult = new OrchestrateChainResultDto();
        $this->staticStrategy->method('execute')->willReturn($staticResult);

        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Implement feature',
        ));

        self::assertSame($staticResult, $result);
    }

    #[Test]
    public function invokeDelegatesDynamicChainToDynamicStrategy(): void
    {
        $chain = $this->createDynamicChain('brainstorm', 'facilitator', ['participant']);

        $this->chainProvider->method('loadChainDefinition')->with('brainstorm')->willReturn($chain);

        $dynamicResult = new OrchestrateChainResultDto(
            synthesis: 'Result',
        );
        $this->dynamicStrategy->method('execute')->willReturn($dynamicResult);

        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'brainstorm',
            task: 'Design a system',
        ));

        self::assertSame($dynamicResult, $result);
        self::assertSame('Result', $result->synthesis);
    }

    #[Test]
    public function invokeCallsResumeWhenResumeDirIsSet(): void
    {
        $chain = $this->createDynamicChain('brainstorm', 'facilitator', ['participant']);

        $this->chainProvider->method('loadChainDefinition')->willReturn($chain);

        $resumeResult = new OrchestrateChainResultDto(
            synthesis: 'Resumed result',
            sessionDir: '/tmp/resume-dir',
        );
        $this->dynamicStrategy->method('resume')->willReturn($resumeResult);

        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'brainstorm',
            task: 'Test',
            resumeDir: '/tmp/resume-dir',
        ));

        self::assertSame($resumeResult, $result);
        self::assertSame('/tmp/resume-dir', $result->sessionDir);
    }

    #[Test]
    public function invokeThrowsWhenNoStrategySupportsChain(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No execution strategy found for chain "unknown".');

        $chain = StaticChainDefinitionVo::create(
            name: 'unknown',
            description: '',
            steps: [ChainStepVo::agent(role: 'role', runner: 'pi')],
        );

        $this->chainProvider->method('loadChainDefinition')->willReturn($chain);

        // Both strategies return false for supports()
        $handler = new OrchestrateChainCommandHandler(
            $this->chainProvider,
            [], // empty strategies
        );

        ($handler)(new OrchestrateChainCommand(
            chainName: 'unknown',
            task: 'Test',
        ));
    }

    // --- Helpers ---

    /**
     * @param list<string> $participants
     */
    private function createDynamicChain(
        string $name,
        string $facilitator,
        array $participants,
        int $maxRounds = 10,
    ): DynamicChainDefinitionVo {
        return DynamicChainDefinitionVo::create(
            name: $name,
            description: '',
            facilitator: $facilitator,
            participants: $participants,
            maxRounds: $maxRounds,
            brainstormSystemPrompt: 'Base system prompt',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Start %s',
            facilitatorContinuePrompt: 'Cont %s %s %s',
            facilitatorFinalizePrompt: 'Final %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Ctx %s %s',
        );
    }
}
