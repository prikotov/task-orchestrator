<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Service\Chain;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Chain\StaticExecutionStrategy;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\ExecuteStaticChainServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainExecutionTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Integration\ChainDefinitionProviderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionChainInfoVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStaticChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticChainResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;

#[CoversClass(StaticExecutionStrategy::class)]
final class StaticExecutionStrategyTest extends TestCase
{
    private ExecuteStaticChainServiceInterface $staticChainExecutor;
    private ChainDefinitionProviderInterface $chainProvider;
    private StaticExecutionStrategy $strategy;

    protected function setUp(): void
    {
        $this->staticChainExecutor = $this->createMock(ExecuteStaticChainServiceInterface::class);
        $this->chainProvider = $this->createMock(ChainDefinitionProviderInterface::class);
        $this->strategy = new StaticExecutionStrategy($this->staticChainExecutor, $this->chainProvider);
    }

    // --- supports() ---

    #[Test]
    public function supportsReturnsTrueForStaticChain(): void
    {
        $chainInfo = new ExecutionChainInfoVo('static-test', ChainExecutionTypeEnum::staticType);

        self::assertTrue($this->strategy->supports($chainInfo));
    }

    #[Test]
    public function supportsReturnsFalseForDynamicChain(): void
    {
        $chainInfo = new ExecutionChainInfoVo('dynamic-test', ChainExecutionTypeEnum::dynamicType);

        self::assertFalse($this->strategy->supports($chainInfo));
    }

    // --- execute() ---

    #[Test]
    public function executeDelegatesToStaticChainExecutor(): void
    {
        $chainInfo = new ExecutionChainInfoVo('static-test', ChainExecutionTypeEnum::staticType);
        $config = $this->createStaticConfig();

        $this->chainProvider->method('loadStaticChainConfig')
            ->with('static-test')
            ->willReturn($config);

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

        $result = $this->strategy->execute($chainInfo, new OrchestrateChainCommand(
            chainName: 'static-test',
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

        $chainInfo = new ExecutionChainInfoVo('static-test', ChainExecutionTypeEnum::staticType);
        $this->strategy->resume($chainInfo, new OrchestrateChainCommand(
            chainName: 'static-test',
            task: 'Test',
            resumeDir: '/tmp/resume',
        ));
    }

    // --- Helpers ---

    private function createStaticConfig(): ExecutionStaticChainConfigVo
    {
        return new ExecutionStaticChainConfigVo(
            name: 'static-test',
            steps: [],
            fixIterations: [],
            budget: null,
            timeout: null,
            roles: [],
            defaultRetryPolicy: null,
        );
    }

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
}
