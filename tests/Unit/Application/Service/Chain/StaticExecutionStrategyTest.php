<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Service\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\StaticExecutionStrategy;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\ExecuteStaticChainServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Contract\Chain\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticChainResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\ChainDefinition\ChainExecutionDefinitionMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
use LogicException;

#[CoversClass(StaticExecutionStrategy::class)]
final class StaticExecutionStrategyTest extends TestCase
{
    private ExecuteStaticChainServiceInterface $staticChainExecutor;
    private StaticExecutionStrategy $strategy;

    protected function setUp(): void
    {
        $this->staticChainExecutor = $this->createMock(ExecuteStaticChainServiceInterface::class);
        $chainLoader = $this->createMock(ChainLoaderInterface::class);
        $mapper = new ChainExecutionDefinitionMapper($chainLoader);
        $this->strategy = new StaticExecutionStrategy($this->staticChainExecutor, $mapper);
    }

    // --- supports() ---

    #[Test]
    public function supportsReturnsTrueForStaticChain(): void
    {
        $chain = $this->createStaticChain();

        self::assertTrue($this->strategy->supports($chain));
    }

    #[Test]
    public function supportsReturnsFalseForDynamicChain(): void
    {
        $chain = $this->createDynamicChain();

        self::assertFalse($this->strategy->supports($chain));
    }

    // --- execute() ---

    #[Test]
    public function executeDelegatesToStaticChainExecutor(): void
    {
        $chain = $this->createStaticChain();

        $staticResult = $this->createStaticChainResult([
            new StaticStepResultVo(
                role: 'analyst',
                runner: 'pi',
                outputText: 'result',
                inputTokens: 100,
                outputTokens: 200,
                cost: 0.01,
                duration: 1.0,
                isError: false,
            ),
        ]);
        $this->staticChainExecutor->method('execute')->willReturn($staticResult);

        $result = $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Do work',
        ));

        self::assertInstanceOf(OrchestrateChainResultDto::class, $result);
        self::assertCount(1, $result->stepResults);
        self::assertSame('analyst', $result->stepResults[0]->role);
        self::assertSame('result', $result->stepResults[0]->outputText);
    }

    // --- resume() ---

    #[Test]
    public function resumeThrowsLogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Static chain does not support resume.');

        $chain = $this->createStaticChain();
        $this->strategy->resume($chain, new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Test',
            resumeDir: '/tmp/resume',
        ));
    }

    // --- Helpers ---

    /**
     * @param list<StaticStepResultVo> $steps
     */
    private function createStaticChainResult(array $steps): StaticChainResultVo
    {
        $totalInput = array_sum(array_map(static fn(StaticStepResultVo $s): int => $s->inputTokens, $steps));
        $totalOutput = array_sum(array_map(static fn(StaticStepResultVo $s): int => $s->outputTokens, $steps));
        $totalCost = array_sum(array_map(static fn(StaticStepResultVo $s): float => $s->cost, $steps));

        return new StaticChainResultVo(
            stepResults: $steps,
            totalTime: 3.5,
            totalInputTokens: $totalInput,
            totalOutputTokens: $totalOutput,
            totalCost: $totalCost,
            budgetExceeded: false,
            budgetLimit: 0.0,
            budgetExceededRole: null,
            totalIterations: 1,
        );
    }

    private function createStaticChain(): ChainDefinitionInterface
    {
        return StaticChainDefinitionVo::create(
            name: 'static-test',
            description: 'Test static chain',
            steps: [
                ChainStepVo::agent(role: 'system_analyst', runner: 'pi'),
            ],
        );
    }

    private function createDynamicChain(): ChainDefinitionInterface
    {
        return DynamicChainDefinitionVo::create(
            name: 'dynamic-test',
            description: '',
            facilitator: 'facilitator',
            participants: ['participant'],
            maxRounds: 5,
            brainstormSystemPrompt: 'Base',
            facilitatorAppendPrompt: 'Fac %s',
            facilitatorStartPrompt: 'Start %s',
            facilitatorContinuePrompt: 'Cont %s %s %s',
            facilitatorFinalizePrompt: 'Final %s %s',
            participantAppendPrompt: 'Part %s',
            participantUserPrompt: 'Ctx %s %s',
        );
    }
}
