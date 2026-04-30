<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Service\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ExecuteStaticChainServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\StaticExecutionStrategy;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;
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

        $expected = new OrchestrateChainResultDto();
        $this->staticChainExecutor->method('execute')->willReturn($expected);

        $result = $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Do work',
        ));

        self::assertSame($expected, $result);
    }

    #[Test]
    public function executePassesCliTimeout(): void
    {
        $chain = $this->createStaticChain();

        $capturedTimeout = null;
        $this->staticChainExecutor->method('execute')
            ->willReturnCallback(function (
                ChainDefinitionVo $c,
                string $t,
                ?string $w,
                int $timeout,
            ) use (&$capturedTimeout): OrchestrateChainResultDto {
                $capturedTimeout = $timeout;

                return new OrchestrateChainResultDto();
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
                ChainDefinitionVo $c,
                string $t,
                ?string $w,
                int $timeout,
            ) use (&$capturedTimeout): OrchestrateChainResultDto {
                $capturedTimeout = $timeout;

                return new OrchestrateChainResultDto();
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
                ChainDefinitionVo $c,
                string $t,
                ?string $w,
                int $timeout,
                ?AuditLoggerInterface $a,
                bool $noContextFiles,
            ) use (&$capturedNoContextFiles): OrchestrateChainResultDto {
                $capturedNoContextFiles = $noContextFiles;

                return new OrchestrateChainResultDto();
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
                ChainDefinitionVo $c,
                string $t,
                ?string $w,
                int $timeout,
                ?AuditLoggerInterface $logger,
            ) use (&$capturedLogger): OrchestrateChainResultDto {
                $capturedLogger = $logger;

                return new OrchestrateChainResultDto();
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

    // --- Helpers ---

    private function createStaticChain(): ChainDefinitionVo
    {
        return ChainDefinitionVo::createFromSteps(
            name: 'static-test',
            description: 'Test static chain',
            steps: [
                ChainStepVo::agent(role: 'system_analyst', runner: 'pi'),
            ],
        );
    }

    private function createDynamicChain(): ChainDefinitionVo
    {
        return ChainDefinitionVo::createFromDynamic(
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
