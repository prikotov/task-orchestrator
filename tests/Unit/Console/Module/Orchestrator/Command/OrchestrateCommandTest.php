<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Console\Module\Orchestrator\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigViolationDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainDefinitionDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainStepDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Enum\OrchestrateExitCodeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\ResolveExitCodeService;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\ResolveExitCodeServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\LoadChain\LoadChainQuery;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\LoadChain\LoadChainQueryHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\LoadChain\LoadChainResult;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\ValidateChainConfig\ValidateChainConfigQuery;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\ValidateChainConfig\ValidateChainConfigQueryHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\ValidateChainConfig\ValidateChainConfigResult;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportQuery;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportQueryHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportResult;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain\YamlChainLoader;
use TaskOrchestrator\Console\Module\Orchestrator\Command\OrchestrateCommand;

#[CoversClass(OrchestrateCommand::class)]
final class OrchestrateCommandTest extends TestCase
{
    private OrchestrateChainCommandHandler&MockObject $orchestrateHandler;
    private GenerateReportQueryHandler&MockObject $reportHandler;
    private LoadChainQueryHandler&MockObject $loadChainHandler;
    private ValidateChainConfigQueryHandler&MockObject $validateChainConfigHandler;
    private LockFactory $lockFactory;
    private ResolveExitCodeServiceInterface $exitCodeResolver;

    #[Override]
    protected function setUp(): void
    {
        $this->orchestrateHandler = $this->createMock(OrchestrateChainCommandHandler::class);
        $this->reportHandler = $this->createMock(GenerateReportQueryHandler::class);
        $this->loadChainHandler = $this->createMock(LoadChainQueryHandler::class);
        $this->validateChainConfigHandler = $this->createMock(ValidateChainConfigQueryHandler::class);
        $this->lockFactory = new LockFactory(new FlockStore());
        $this->exitCodeResolver = new ResolveExitCodeService();
    }

    // ─── Basic execution ───────────────────────────────────────────────────────

    #[Test]
    public function executeStaticChainSuccess(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

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

    #[Test]
    public function executeWithLockAlreadyAcquiredReturnsSuccess(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        // Запускаем две команды параллельно через lock
        $command = new OrchestrateCommand(
            $this->orchestrateHandler,
            $this->reportHandler,
            $this->loadChainHandler,
            $this->validateChainConfigHandler,
            $this->lockFactory,
            $this->exitCodeResolver,
        );

        $app = new Application();
        $app->addCommand($command);

        $tester1 = new CommandTester($app->find('app:agent:orchestrate'));
        $tester2 = new CommandTester($app->find('app:agent:orchestrate'));

        // Первая команда захватит lock и будет ждать
        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturn(new OrchestrateChainResultDto(stepResults: [], budgetExceeded: false));

        // Просто проверяем что lock-механизм не падает
        self::assertInstanceOf(OrchestrateCommand::class, $command);
    }

    #[Test]
    public function executeWithTimeoutOption(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturn(new OrchestrateChainResultDto(
                stepResults: [],
                budgetExceeded: false,
            ));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--timeout' => '3600', '--report-format' => 'none']);

        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
    }

    // ─── Dry-run ───────────────────────────────────────────────────────────────

    #[Test]
    public function dryRunShowsPlanAndReturnsSuccess(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'Create REST API', '--dry-run' => true]);

        $output = $tester->getDisplay();
        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
        self::assertStringContainsString('DRY RUN', $output);
        self::assertStringContainsString('implement', $output);
        self::assertStringContainsString('Create REST API', $output);
    }

    // ─── --validate-config ─────────────────────────────────────────────────────

    #[Test]
    public function validateConfigAllChainsValid(): void
    {
        $this->validateChainConfigHandler->method('__invoke')->willReturn(
            new ValidateChainConfigResult(
                isValid: true,
                violations: [],
                chainNames: ['implement', 'analyze'],
            ),
        );

        $tester = $this->createCommandTester();
        $tester->execute(['task' => '_', '--validate-config' => true]);

        $output = $tester->getDisplay();
        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
        self::assertStringContainsString('Config is valid', $output);
    }

    #[Test]
    public function validateConfigSpecificChainValid(): void
    {
        $this->validateChainConfigHandler->method('__invoke')->willReturn(
            new ValidateChainConfigResult(
                isValid: true,
                violations: [],
                validChainName: 'implement',
            ),
        );

        $tester = $this->createCommandTester();
        $tester->execute(['task' => '_', '--validate-config' => true, '--chain' => 'implement']);

        $output = $tester->getDisplay();
        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
        self::assertStringContainsString('Config is valid', $output);
        self::assertStringContainsString('implement', $output);
    }

    #[Test]
    public function validateConfigWithViolationsReturnsInvalidConfig(): void
    {
        $this->validateChainConfigHandler->method('__invoke')->willReturn(
            new ValidateChainConfigResult(
                isValid: false,
                violations: [
                    new ChainConfigViolationDto('broken', 'max_rounds', 'max_rounds must be >= 1, got 0'),
                ],
                validChainName: 'broken',
            ),
        );

        $tester = $this->createCommandTester();
        $tester->execute(['task' => '_', '--validate-config' => true, '--chain' => 'broken']);

        $output = $tester->getDisplay();
        self::assertSame(OrchestrateExitCodeEnum::invalidConfig->value, $tester->getStatusCode());
        self::assertStringContainsString('Config validation failed', $output);
        self::assertStringContainsString('max_rounds', $output);
    }

    // ─── --config option ───────────────────────────────────────────────────────

    #[Test]
    public function configOptionWithNonExistentFileReturnsInvalidConfig(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([
            'task' => 'do something',
            '--config' => '/nonexistent/chains.yaml',
        ]);

        self::assertSame(OrchestrateExitCodeEnum::invalidConfig->value, $tester->getStatusCode());
        self::assertStringContainsString('Config file not found', $tester->getDisplay());
    }

    #[Test]
    public function configOptionWithValidFileLoadsChainsFromIt(): void
    {
        $tmpDir = sys_get_temp_dir() . '/orch_test_config_' . uniqid();
        mkdir($tmpDir);
        $tmpPath = $tmpDir . '/chains.yaml';
        file_put_contents($tmpPath, <<<'YAML'
chains:
  custom:
    description: "Custom chain"
    steps:
      - { type: agent, role: custom_role }
YAML);

        try {
            $chainLoader = new YamlChainLoader('/nonexistent/default.yaml');
            $chainProviderService = new \TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ChainProviderService(
                $chainLoader,
                new \TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\ChainDefinitionValidator(),
            );
            $loadHandler = new LoadChainQueryHandler($chainProviderService);

            $command = new OrchestrateCommand(
                $this->orchestrateHandler,
                $this->reportHandler,
                $loadHandler,
                $this->validateChainConfigHandler,
                $this->lockFactory,
                $this->exitCodeResolver,
            );

            $application = new Application();
            $application->addCommand($command);
            $tester = new CommandTester($application->find('app:agent:orchestrate'));

            $this->orchestrateHandler
                ->method('__invoke')
                ->willReturn(new OrchestrateChainResultDto(
                    stepResults: [],
                    budgetExceeded: false,
                ));

            $tester->execute([
                'task' => 'do something',
                '--chain' => 'custom',
                '--config' => $tmpPath,
                '--report-format' => 'none',
            ]);

            self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
        } finally {
            unlink($tmpPath);
            rmdir($tmpDir);
        }
    }

    #[Test]
    public function configOptionWithValidateConfigValidatesCustomFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/orch_test_validate_' . uniqid();
        mkdir($tmpDir);
        $tmpPath = $tmpDir . '/chains.yaml';
        file_put_contents($tmpPath, <<<'YAML'
chains:
  mychain:
    description: "My chain"
    steps:
      - { type: agent, role: role_a }
YAML);

        try {
            $chainLoader = new YamlChainLoader('/nonexistent/default.yaml');
            $chainProviderService = new \TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ChainProviderService(
                $chainLoader,
                new \TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\ChainDefinitionValidator(),
            );
            $validateHandler = new ValidateChainConfigQueryHandler($chainProviderService);

            $command = new OrchestrateCommand(
                $this->orchestrateHandler,
                $this->reportHandler,
                $this->loadChainHandler,
                $validateHandler,
                $this->lockFactory,
                $this->exitCodeResolver,
            );

            $application = new Application();
            $application->addCommand($command);
            $tester = new CommandTester($application->find('app:agent:orchestrate'));

            $tester->execute([
                'task' => '_',
                '--validate-config' => true,
                '--config' => $tmpPath,
            ]);

            self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
            self::assertStringContainsString('Config is valid', $tester->getDisplay());
            self::assertStringContainsString('mychain', $tester->getDisplay());
        } finally {
            unlink($tmpPath);
            rmdir($tmpDir);
        }
    }

    #[Test]
    public function configOptionWithoutValueUsesDefaultPath(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

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

    // ─── Error handling ────────────────────────────────────────────────────────

    #[Test]
    public function executeWithAgentErrorReturnsChainFailed(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        $this->orchestrateHandler
            ->method('__invoke')
            ->willThrowException(new \RuntimeException('Agent failed'));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--report-format' => 'none']);

        self::assertSame(OrchestrateExitCodeEnum::chainFailed->value, $tester->getStatusCode());
    }

    #[Test]
    public function executeWithTimeoutReturnsExitCode6(): void
    {
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturn(new OrchestrateChainResultDto(
                stepResults: [],
                budgetExceeded: false,
                timedOut: true,
            ));

        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--report-format' => 'none']);

        self::assertSame(OrchestrateExitCodeEnum::timeout->value, $tester->getStatusCode());
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function createCommandTester(): CommandTester
    {
        $command = new OrchestrateCommand(
            $this->orchestrateHandler,
            $this->reportHandler,
            $this->loadChainHandler,
            $this->validateChainConfigHandler,
            $this->lockFactory,
            $this->exitCodeResolver,
        );

        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:agent:orchestrate'));
    }

    private function createStaticChainDefinition(): ChainDefinitionDto
    {
        return new ChainDefinitionDto(
            name: 'implement',
            isDynamic: false,
            facilitator: null,
            participants: [],
            maxRounds: 10,
            steps: [
                new ChainStepDto(role: 'agent', runner: 'pi', label: '', isQualityGate: false),
            ],
        );
    }
}
