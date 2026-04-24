<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Console\Module\Orchestrator\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportQuery;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportQueryHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\OrchestrateExitCodeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\ChainNotFoundException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\RoleNotFoundException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Console\Module\Orchestrator\Command\OrchestrateCommand;

#[CoversClass(OrchestrateCommand::class)]
final class OrchestrateCommandTest extends TestCase
{
    private LockFactory&MockObject $lockFactory;
    private ChainLoaderInterface&MockObject $chainLoader;
    private LockInterface&MockObject $lock;

    /** @var \Closure(OrchestrateChainCommand): OrchestrateChainResultDto|null */
    private ?\Closure $orchestrateHandlerCallback = null;

    #[Override]
    protected function setUp(): void
    {
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->chainLoader = $this->createMock(ChainLoaderInterface::class);
        $this->lock = $this->createMock(LockInterface::class);

        $this->lockFactory
            ->method('createLock')
            ->willReturn($this->lock);

        $this->lock->method('acquire')->willReturn(true);
        $this->lock->method('release');
    }

    // ─── resolveExitCodeFromThrowable: ChainNotFoundException → chainNotFound (3) ──

    #[Test]
    public function chainNotFoundExceptionReturnsChainNotFound(): void
    {
        $this->chainLoader
            ->method('load')
            ->willThrowException(new ChainNotFoundException('missing'));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'do something']);

        self::assertSame(OrchestrateExitCodeEnum::chainNotFound->value, $tester->getStatusCode());
    }

    // ─── resolveExitCodeFromThrowable: RoleNotFoundException → invalidConfig (5) ──

    #[Test]
    public function roleNotFoundExceptionReturnsInvalidConfig(): void
    {
        $this->chainLoader
            ->method('load')
            ->willThrowException(new RoleNotFoundException('bad_role'));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'do something']);

        self::assertSame(OrchestrateExitCodeEnum::invalidConfig->value, $tester->getStatusCode());
    }

    // ─── resolveExitCodeFromThrowable: generic exception → chainFailed (1) ──

    #[Test]
    public function genericExceptionReturnsChainFailed(): void
    {
        $this->chainLoader
            ->method('load')
            ->willThrowException(new \RuntimeException('something broke'));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'do something']);

        self::assertSame(OrchestrateExitCodeEnum::chainFailed->value, $tester->getStatusCode());
    }

    // ─── resolveExitCodeFromResult: static success → 0 ──

    #[Test]
    public function staticChainSuccessReturnsZero(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->chainLoader->method('load')->willReturn($chain);

        $this->setOrchestrateHandlerResult(new OrchestrateChainResultDto(
            stepResults: [],
            budgetExceeded: false,
        ));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'do something', '--report-format' => 'none']);

        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
    }

    // ─── resolveExitCodeFromResult: static chain with error step → chainFailed (1) ──

    #[Test]
    public function staticChainWithErrorReturnsChainFailed(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->chainLoader->method('load')->willReturn($chain);

        $this->setOrchestrateHandlerResult(new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'agent',
                    runner: 'pi',
                    outputText: '',
                    inputTokens: 0,
                    outputTokens: 0,
                    cost: 0.0,
                    duration: 1.0,
                    isError: true,
                    errorMessage: 'Agent crashed',
                ),
            ],
            budgetExceeded: false,
        ));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'do something', '--report-format' => 'none']);

        self::assertSame(OrchestrateExitCodeEnum::chainFailed->value, $tester->getStatusCode());
    }

    // ─── resolveExitCodeFromResult: budget exceeded → budgetExceeded (4) ──

    #[Test]
    public function budgetExceededReturnsBudgetExceededCode(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->chainLoader->method('load')->willReturn($chain);

        $this->setOrchestrateHandlerResult(new OrchestrateChainResultDto(
            stepResults: [],
            budgetExceeded: true,
            budgetLimit: 10.0,
            totalCost: 12.0,
        ));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'do something', '--report-format' => 'none']);

        self::assertSame(OrchestrateExitCodeEnum::budgetExceeded->value, $tester->getStatusCode());
    }

    // ─── resolveExitCodeFromResult: dynamic chain with synthesis → success (0) ──

    #[Test]
    public function dynamicChainWithSynthesisReturnsSuccess(): void
    {
        $chain = $this->createDynamicChainDefinition();
        $this->chainLoader->method('load')->willReturn($chain);

        $this->setOrchestrateHandlerResult(new OrchestrateChainResultDto(
            synthesis: 'Done.',
            budgetExceeded: false,
        ));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'do something', '--report-format' => 'none']);

        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
    }

    // ─── resolveExitCodeFromResult: dynamic chain without synthesis → chainFailed (1) ──

    #[Test]
    public function dynamicChainWithoutSynthesisReturnsChainFailed(): void
    {
        $chain = $this->createDynamicChainDefinition();
        $this->chainLoader->method('load')->willReturn($chain);

        $this->setOrchestrateHandlerResult(new OrchestrateChainResultDto(
            synthesis: null,
            budgetExceeded: false,
        ));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'do something', '--report-format' => 'none']);

        self::assertSame(OrchestrateExitCodeEnum::chainFailed->value, $tester->getStatusCode());
    }

    // ─── dry-run → success (0) ──

    #[Test]
    public function dryRunReturnsSuccess(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->chainLoader->method('load')->willReturn($chain);

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'do something', '--dry-run' => true]);

        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
    }

    // ─── budget exceeded takes priority over chain error ──

    #[Test]
    public function budgetExceededTakesPriorityOverStepError(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->chainLoader->method('load')->willReturn($chain);

        $this->setOrchestrateHandlerResult(new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'agent',
                    runner: 'pi',
                    outputText: '',
                    inputTokens: 0,
                    outputTokens: 0,
                    cost: 0.0,
                    duration: 1.0,
                    isError: true,
                    errorMessage: 'Agent crashed',
                ),
            ],
            budgetExceeded: true,
            budgetLimit: 5.0,
            totalCost: 6.0,
        ));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'do something', '--report-format' => 'none']);

        self::assertSame(OrchestrateExitCodeEnum::budgetExceeded->value, $tester->getStatusCode());
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────────

    private function setOrchestrateHandlerResult(OrchestrateChainResultDto $result): void
    {
        $this->orchestrateHandlerCallback = static fn(): OrchestrateChainResultDto => $result;
    }

    private function createCommandTester(): CommandTester
    {
        $callback = $this->orchestrateHandlerCallback;
        $handler = new class($callback) extends OrchestrateChainCommandHandler {
            /** @var \Closure(OrchestrateChainCommand): OrchestrateChainResultDto|null */
            private readonly ?\Closure $callback;

            /**
             * @param \Closure(OrchestrateChainCommand): OrchestrateChainResultDto|null $callback
             */
            public function __construct(?\Closure $callback)
            {
                // phpcs:ignore -- parent constructor intentionally not called: stub
                $this->callback = $callback;
            }

            public function __invoke(OrchestrateChainCommand $command): OrchestrateChainResultDto
            {
                if ($this->callback !== null) {
                    return ($this->callback)($command);
                }

                return new OrchestrateChainResultDto();
            }
        };

        $reportHandler = new class extends GenerateReportQueryHandler {
            public function __construct()
            {
                // phpcs:ignore -- parent constructor intentionally not called: stub
            }

            public function __invoke(GenerateReportQuery $query): GenerateReportResultDto
            {
                return new GenerateReportResultDto(content: '');
            }
        };

        $command = new OrchestrateCommand(
            $handler,
            $reportHandler,
            $this->lockFactory,
            $this->chainLoader,
        );

        $application = new Application();
        $application->add($command);
        $registeredCommand = $application->find('app:agent:orchestrate');

        return new CommandTester($registeredCommand);
    }

    private function createStaticChainDefinition(): ChainDefinitionVo
    {
        return ChainDefinitionVo::createFromSteps(
            name: 'test-static',
            description: 'Test static chain',
            steps: [
                ChainStepVo::agent(role: 'agent', runner: 'pi'),
            ],
        );
    }

    private function createDynamicChainDefinition(): ChainDefinitionVo
    {
        return ChainDefinitionVo::createFromDynamic(
            name: 'test-dynamic',
            description: 'Test dynamic chain',
            facilitator: 'analyst',
            participants: ['dev', 'qa'],
            maxRounds: 3,
            brainstormSystemPrompt: 'System prompt',
            facilitatorAppendPrompt: 'Facilitator append %s',
            facilitatorStartPrompt: 'Facilitator start %s',
            facilitatorContinuePrompt: 'Facilitator continue %s %s %s',
            facilitatorFinalizePrompt: 'Facilitator finalize %s %s',
            participantAppendPrompt: 'Participant append %s',
            participantUserPrompt: 'Participant user %s %s',
        );
    }
}
