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
use Symfony\Component\Lock\SharedLockInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigValidationResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Enum\OrchestrateExitCodeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\ValidateChainConfigServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\ResolveExitCodeService;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportHandlerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportResultDto;
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
    private OrchestrateChainCommandHandler&MockObject $orchestrateHandler;
    private GenerateReportHandlerInterface&MockObject $reportHandler;
    private ValidateChainConfigServiceInterface&MockObject $chainConfigValidator;
    private SharedLockInterface&MockObject $lock;

    #[Override]
    protected function setUp(): void
    {
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->chainLoader = $this->createMock(ChainLoaderInterface::class);
        $this->orchestrateHandler = $this->createMock(OrchestrateChainCommandHandler::class);
        $this->reportHandler = $this->createMock(GenerateReportHandlerInterface::class);
        $this->chainConfigValidator = $this->createMock(ValidateChainConfigServiceInterface::class);
        $this->lock = $this->createMock(SharedLockInterface::class);

        $this->lockFactory
            ->method('createLock')
            ->willReturn($this->lock);

        $this->lock->method('acquire')->willReturn(true);
        $this->lock->method('release');

        $this->reportHandler
            ->method('__invoke')
            ->willReturn(new GenerateReportResultDto(content: '', format: 'text'));
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

        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturn(new OrchestrateChainResultDto(
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

        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturn(new OrchestrateChainResultDto(
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

        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturn(new OrchestrateChainResultDto(
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

        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturn(new OrchestrateChainResultDto(
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

        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturn(new OrchestrateChainResultDto(
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

        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturn(new OrchestrateChainResultDto(
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

    // ─── Lock scenario: already running → success (0) with warning ──

    #[Test]
    public function lockNotAcquiredReturnsSuccessWithWarning(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);
        $lock->expects($this->never())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $command = new OrchestrateCommand(
            $this->orchestrateHandler,
            $this->reportHandler,
            $lockFactory,
            $this->chainLoader,
            new ResolveExitCodeService(),
            $this->chainConfigValidator,
        );

        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($application->find('app:agent:orchestrate'));

        $tester->execute(['task' => 'do something']);

        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
        self::assertStringContainsString('уже выполняется', $tester->getDisplay());
    }

    // ─── Resume scenario: --resume path → handler called with resumeDir ──

    #[Test]
    public function resumeOptionPassesResumeDirToHandler(): void
    {
        $resumeDir = '/tmp/session-abc';

        $this->orchestrateHandler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(static fn(OrchestrateChainCommand $cmd): bool => $cmd->resumeDir === $resumeDir))
            ->willReturn(new OrchestrateChainResultDto(
                synthesis: 'Resumed result.',
                budgetExceeded: false,
            ));

        $tester = $this->createCommandTester();
        $tester->execute([
            'task' => 'continue task',
            '--resume' => $resumeDir,
            '--report-format' => 'none',
        ]);

        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
    }

    #[Test]
    public function resumeWithoutSynthesisReturnsChainFailed(): void
    {
        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturn(new OrchestrateChainResultDto(
                synthesis: null,
                budgetExceeded: false,
            ));

        $tester = $this->createCommandTester();
        $tester->execute([
            'task' => 'continue task',
            '--resume' => '/tmp/session-fail',
            '--report-format' => 'none',
        ]);

        self::assertSame(OrchestrateExitCodeEnum::chainFailed->value, $tester->getStatusCode());
    }

    // ─── --validate-config: valid config → success (0) ──

    #[Test]
    public function validateConfigValidReturnsSuccess(): void
    {
        $this->chainConfigValidator
            ->method('validateAll')
            ->willReturn(new ChainConfigValidationResultDto(
                isValid: true,
                errors: [],
                validatedChains: ['implement', 'analyze'],
            ));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => '_', '--validate-config' => true]);

        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
        self::assertStringContainsString('Config is valid', $tester->getDisplay());
    }

    // ─── --validate-config: invalid config → invalidConfig (5) ──

    #[Test]
    public function validateConfigInvalidReturnsInvalidConfig(): void
    {
        $this->chainConfigValidator
            ->method('validateAll')
            ->willReturn(new ChainConfigValidationResultDto(
                isValid: false,
                errors: [
                    new \TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigValidationErrorDto(
                        chainName: 'broken',
                        message: 'Missing field X',
                        field: 'steps[0].role',
                    ),
                ],
                validatedChains: ['broken'],
            ));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => '_', '--validate-config' => true]);

        self::assertSame(OrchestrateExitCodeEnum::invalidConfig->value, $tester->getStatusCode());
        self::assertStringContainsString('Config validation failed', $tester->getDisplay());
    }

    // ─── --validate-config --chain=<name>: validates specific chain ──

    #[Test]
    public function validateConfigSpecificChainValidReturnsSuccess(): void
    {
        $this->chainConfigValidator
            ->method('validateChain')
            ->willReturn(new ChainConfigValidationResultDto(
                isValid: true,
                errors: [],
                validatedChains: ['hotfix'],
            ));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => '_', '--validate-config' => true, '--chain' => 'hotfix']);

        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
        self::assertStringContainsString('hotfix', $tester->getDisplay());
    }

    // ─── --validate-config does not start orchestration (handler not called) ──

    #[Test]
    public function validateConfigDoesNotCallOrchestrateHandler(): void
    {
        $this->chainConfigValidator
            ->method('validateAll')
            ->willReturn(new ChainConfigValidationResultDto(
                isValid: true,
                errors: [],
                validatedChains: ['implement'],
            ));

        $this->orchestrateHandler
            ->expects($this->never())
            ->method('__invoke');

        $tester = $this->createCommandTester();
        $tester->execute(['task' => '_', '--validate-config' => true]);

        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────────

    private function createCommandTester(): CommandTester
    {
        $command = new OrchestrateCommand(
            $this->orchestrateHandler,
            $this->reportHandler,
            $this->lockFactory,
            $this->chainLoader,
            new ResolveExitCodeService(),
            $this->chainConfigValidator,
        );

        $application = new Application();
        $application->addCommand($command);
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
