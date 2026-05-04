<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Service\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\StaticExecution\Application\Service\ExecuteStaticChainServiceInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Service\Chain\StaticExecutionStrategy;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\ValueObject\StaticChainResultVo;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\ValueObject\StaticStepResultVo;
use LogicException;

#[CoversClass(StaticExecutionStrategy::class)]
final class StaticExecutionStrategyTest extends TestCase
{
    private ExecuteStaticChainServiceInterface $staticChainExecutor;
    private StaticExecutionStrategy $strategy;

    protected function setUp(): void
    {
        $this->staticChainExecutor = $this->createMock(ExecuteStaticChainServiceInterface::class);
        $this->strategy = new StaticExecutionStrategy($this->staticChainExecutor);
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

    #[Test]
    public function executePassesCliTimeout(): void
    {
        $chain = $this->createStaticChain();

        $capturedTimeout = null;
        $this->staticChainExecutor->method('execute')
            ->willReturnCallback(function (
                StaticChainDefinitionVo $c,
                string $t,
                ?string $w,
                int $timeout,
            ) use (&$capturedTimeout): StaticChainResultVo {
                $capturedTimeout = $timeout;

                return $this->createStaticChainResult([]);
            });

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Do work',
            timeout: 600,
        ));

        self::assertSame(600, $capturedTimeout);
    }

    #[Test]
    public function executeFallsBackToDefaultTimeout(): void
    {
        $chain = $this->createStaticChain();

        $capturedTimeout = null;
        $this->staticChainExecutor->method('execute')
            ->willReturnCallback(function (
                StaticChainDefinitionVo $c,
                string $t,
                ?string $w,
                int $timeout,
            ) use (&$capturedTimeout): StaticChainResultVo {
                $capturedTimeout = $timeout;

                return $this->createStaticChainResult([]);
            });

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Do work',
        ));

        self::assertSame(300, $capturedTimeout);
    }

    #[Test]
    public function executePassesNoContextFiles(): void
    {
        $chain = $this->createStaticChain();

        $capturedNoContextFiles = null;
        $this->staticChainExecutor->method('execute')
            ->willReturnCallback(function (
                StaticChainDefinitionVo $c,
                string $t,
                ?string $w,
                int $timeout,
                ?object $a,
                bool $noContextFiles,
            ) use (&$capturedNoContextFiles): StaticChainResultVo {
                $capturedNoContextFiles = $noContextFiles;

                return $this->createStaticChainResult([]);
            });

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Do work',
            noContextFiles: true,
        ));

        self::assertTrue($capturedNoContextFiles);
    }

    #[Test]
    public function executePassesNullAuditLogger(): void
    {
        $chain = $this->createStaticChain();

        $capturedLogger = 'not-null';
        $this->staticChainExecutor->method('execute')
            ->willReturnCallback(function (
                StaticChainDefinitionVo $c,
                string $t,
                ?string $w,
                int $timeout,
                ?object $logger,
            ) use (&$capturedLogger): StaticChainResultVo {
                $capturedLogger = $logger;

                return $this->createStaticChainResult([]);
            });

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Do work',
        ));

        self::assertNull($capturedLogger);
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

    // --- VO → DTO mapping ---

    #[Test]
    public function executeMapsStaticChainResultVoToDto(): void
    {
        $chain = $this->createStaticChain();

        $staticResult = $this->createStaticChainResult([
            new StaticStepResultVo(
                role: 'analyst',
                runner: 'pi',
                outputText: 'Analysis result',
                inputTokens: 100,
                outputTokens: 200,
                cost: 0.01,
                duration: 1.5,
                isError: false,
                timedOut: false,
            ),
            new StaticStepResultVo(
                role: 'developer',
                runner: 'pi',
                outputText: 'Impl result',
                inputTokens: 150,
                outputTokens: 300,
                cost: 0.02,
                duration: 2.0,
                isError: false,
                timedOut: false,
            ),
        ]);
        $this->staticChainExecutor->method('execute')->willReturn($staticResult);

        $result = $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Do work',
        ));

        // Aggregated metrics mapped correctly
        self::assertSame(250, $result->totalInputTokens);
        self::assertSame(500, $result->totalOutputTokens);
        self::assertSame(0.03, $result->totalCost);
        self::assertGreaterThan(0.0, $result->totalTime);
        self::assertFalse($result->budgetExceeded);
        self::assertSame(1, $result->totalIterations);
        self::assertFalse($result->timedOut);
    }

    #[Test]
    public function executeMapsTimedOutFlag(): void
    {
        $chain = $this->createStaticChain();

        $staticResult = $this->createStaticChainResult([
            new StaticStepResultVo(
                role: 'analyst',
                runner: 'pi',
                outputText: '',
                inputTokens: 0,
                outputTokens: 0,
                cost: 0.0,
                duration: 5.0,
                isError: true,
                errorMessage: 'timeout',
                timedOut: true,
            ),
        ]);
        $this->staticChainExecutor->method('execute')->willReturn($staticResult);

        $result = $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Do work',
        ));

        self::assertTrue($result->timedOut);
        self::assertTrue($result->stepResults[0]->timedOut);
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
