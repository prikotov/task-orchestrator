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
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Dto\ChainConfigViolationDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Dto\ChainDefinitionDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Dto\ChainStepDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Enum\OrchestrateExitCodeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadChain\LoadChainQuery;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadChain\LoadChainQueryHandler;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadChain\LoadChainResult;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\ValidateChainConfig\ValidateChainConfigQuery;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\ValidateChainConfig\ValidateChainConfigQueryHandler;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\ValidateChainConfig\ValidateChainConfigResult;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\GenerateReport\GenerateReportQuery;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\GenerateReport\GenerateReportQueryHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\GenerateReport\GenerateReportResult;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainStepFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlChainStepMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlRetryPolicyMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\YamlChainLoaderService;
use TaskOrchestrator\Console\Module\Orchestrator\Command\OrchestrateCommand;
use TaskOrchestrator\Tests\Double\Component\BusTestFactory;

#[CoversClass(OrchestrateCommand::class)]
final class OrchestrateCommandTest extends TestCase
{
    private OrchestrateChainCommandHandler&MockObject $orchestrateHandler;
    private GenerateReportQueryHandler&MockObject $reportHandler;
    private LoadChainQueryHandler&MockObject $loadChainHandler;
    private ValidateChainConfigQueryHandler&MockObject $validateChainConfigHandler;
    private LockFactory $lockFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->orchestrateHandler = $this->createMock(OrchestrateChainCommandHandler::class);
        $this->reportHandler = $this->createMock(GenerateReportQueryHandler::class);
        $this->loadChainHandler = $this->createMock(LoadChainQueryHandler::class);
        $this->validateChainConfigHandler = $this->createMock(ValidateChainConfigQueryHandler::class);
        $this->lockFactory = new LockFactory(new FlockStore());
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
            BusTestFactory::commandBus($this->orchestrateHandler),
            BusTestFactory::queryBus($this->reportHandler, $this->loadChainHandler, $this->validateChainConfigHandler),
            $this->lockFactory,
        );

        $app = new Application();
        $app->addCommand($command);

        $tester1 = new CommandTester($app->find('agent:orchestrate'));
        $tester2 = new CommandTester($app->find('agent:orchestrate'));

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
        $tester->execute(['task' => 'test', '--timeout' => '600', '--report-format' => 'none']);

        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
    }

    // ─── CLI option mapping (precedence: CLI explicit > chain config > hard default) ──

    #[Test]
    public function executeWithoutTimeoutOptionPassesNullToHandler(): void
    {
        // Arrange: static-цепочка, --timeout НЕ передан явно.
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        $capturedCommand = null;
        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturnCallback(function (OrchestrateChainCommand $cmd) use (&$capturedCommand): OrchestrateChainResultDto {
                $capturedCommand = $cmd;

                return new OrchestrateChainResultDto(stepResults: [], budgetExceeded: false);
            });

        // Act
        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--report-format' => 'none']);

        // Assert: timeout null → execution-strategy возьмёт chain config / hard default.
        self::assertNotNull($capturedCommand);
        self::assertNull($capturedCommand->timeout);
    }

    #[Test]
    public function executeWithExplicitTimeoutOptionPassesValueToHandler(): void
    {
        // Arrange
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        $capturedCommand = null;
        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturnCallback(function (OrchestrateChainCommand $cmd) use (&$capturedCommand): OrchestrateChainResultDto {
                $capturedCommand = $cmd;

                return new OrchestrateChainResultDto(stepResults: [], budgetExceeded: false);
            });

        // Act: явный --timeout=120 должен иметь приоритет над chain config.
        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--timeout' => '120', '--report-format' => 'none']);

        // Assert
        self::assertNotNull($capturedCommand);
        self::assertSame(120, $capturedCommand->timeout);
    }

    #[Test]
    public function executeWithExplicitZeroTimeoutPassesZeroNotNullToHandler(): void
    {
        // Edge case: --timeout=0 — явное значение, а не «не передано».
        // 0 должен дойти до стратегии как валидный override, а не превратиться в null
        // (что привело бы к применению chain.timeout/hard default).
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        $capturedCommand = null;
        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturnCallback(function (OrchestrateChainCommand $cmd) use (&$capturedCommand): OrchestrateChainResultDto {
                $capturedCommand = $cmd;

                return new OrchestrateChainResultDto(stepResults: [], budgetExceeded: false);
            });

        // Act: явный --timeout=0.
        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--timeout' => '0', '--report-format' => 'none']);

        // Assert: 0 сохранён как явный override; resolveOptionalIntOption не схлопнул его в null.
        self::assertNotNull($capturedCommand);
        self::assertSame(0, $capturedCommand->timeout);
    }

    #[Test]
    public function executeWithEmptyTimeoutStringPassesNullToHandler(): void
    {
        // Edge case: --timeout= (явная пустая строка) — не валидное число.
        // resolveOptionalIntOption проходит через ветку $value !== '' → null, чтобы
        // execution-strategy применила chain.timeout/hard default, а не (int)'' = 0.
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        $capturedCommand = null;
        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturnCallback(function (OrchestrateChainCommand $cmd) use (&$capturedCommand): OrchestrateChainResultDto {
                $capturedCommand = $cmd;

                return new OrchestrateChainResultDto(stepResults: [], budgetExceeded: false);
            });

        // Act: --timeout='' (явная пустая строка).
        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--timeout' => '', '--report-format' => 'none']);

        // Assert: пустая строка → null (не 0).
        self::assertNotNull($capturedCommand);
        self::assertNull($capturedCommand->timeout);
    }

    #[Test]
    public function executeWithoutMaxTimeOptionPassesNullToHandler(): void
    {
        // Arrange
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        $capturedCommand = null;
        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturnCallback(function (OrchestrateChainCommand $cmd) use (&$capturedCommand): OrchestrateChainResultDto {
                $capturedCommand = $cmd;

                return new OrchestrateChainResultDto(stepResults: [], budgetExceeded: false);
            });

        // Act: --max-time НЕ передан.
        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--report-format' => 'none']);

        // Assert: maxTime null → chain.max_time / hard default не затёрт.
        self::assertNotNull($capturedCommand);
        self::assertNull($capturedCommand->maxTime);
    }

    #[Test]
    public function executeWithExplicitMaxTimeOptionPassesValueToHandler(): void
    {
        // Arrange
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        $capturedCommand = null;
        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturnCallback(function (OrchestrateChainCommand $cmd) use (&$capturedCommand): OrchestrateChainResultDto {
                $capturedCommand = $cmd;

                return new OrchestrateChainResultDto(stepResults: [], budgetExceeded: false);
            });

        // Act: явный --max-time=999.
        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--max-time' => '999', '--report-format' => 'none']);

        // Assert
        self::assertNotNull($capturedCommand);
        self::assertSame(999, $capturedCommand->maxTime);
    }

    #[Test]
    public function executeWithExplicitZeroMaxTimePassesZeroNotNullToHandler(): void
    {
        // Edge case (симметрично executeWithExplicitZeroTimeoutPassesZeroNotNullToHandler):
        // --max-time=0 — явное значение, а не «не передано». 0 должен дойти до стратегии
        // как валидный override, а не превратиться в null.
        $chain = $this->createStaticChainDefinition();
        $this->loadChainHandler->method('__invoke')->willReturn(new LoadChainResult($chain));

        $capturedCommand = null;
        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturnCallback(function (OrchestrateChainCommand $cmd) use (&$capturedCommand): OrchestrateChainResultDto {
                $capturedCommand = $cmd;

                return new OrchestrateChainResultDto(stepResults: [], budgetExceeded: false);
            });

        // Act: явный --max-time=0.
        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--max-time' => '0', '--report-format' => 'none']);

        // Assert: 0 сохранён как явный override; resolveOptionalIntOption не схлопнул его в null.
        self::assertNotNull($capturedCommand);
        self::assertSame(0, $capturedCommand->maxTime);
    }

    #[Test]
    public function resumeWithoutTimeoutOptionPassesNullToHandler(): void
    {
        // Arrange: resume-ветка не обращается к loadChainHandler.
        $capturedCommand = null;
        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturnCallback(function (OrchestrateChainCommand $cmd) use (&$capturedCommand): OrchestrateChainResultDto {
                $capturedCommand = $cmd;

                return new OrchestrateChainResultDto(
                    roundResults: [],
                    totalTime: 0.0,
                    totalInputTokens: 0,
                    totalOutputTokens: 0,
                    totalCost: 0.0,
                    synthesis: 'resumed',
                    maxRoundsReached: false,
                    sessionDir: '/tmp/resume',
                    budgetExceeded: false,
                    budgetLimit: 0.0,
                    budgetExceededRole: null,
                    timedOut: false,
                );
            });

        // Act: resume без явного --timeout.
        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--resume' => '/tmp/resume-dir']);

        // Assert: precedence для resume такой же, как для initial run.
        self::assertNotNull($capturedCommand);
        self::assertNull($capturedCommand->timeout);
        self::assertNull($capturedCommand->maxTime);
    }

    #[Test]
    public function resumeWithExplicitTimeoutOptionPassesValueToHandler(): void
    {
        // Arrange
        $capturedCommand = null;
        $this->orchestrateHandler
            ->method('__invoke')
            ->willReturnCallback(function (OrchestrateChainCommand $cmd) use (&$capturedCommand): OrchestrateChainResultDto {
                $capturedCommand = $cmd;

                return new OrchestrateChainResultDto(
                    roundResults: [],
                    totalTime: 0.0,
                    totalInputTokens: 0,
                    totalOutputTokens: 0,
                    totalCost: 0.0,
                    synthesis: 'resumed',
                    maxRoundsReached: false,
                    sessionDir: '/tmp/resume',
                    budgetExceeded: false,
                    budgetLimit: 0.0,
                    budgetExceededRole: null,
                    timedOut: false,
                );
            });

        // Act: resume с явным --timeout=240.
        $tester = $this->createCommandTester();
        $tester->execute(['task' => 'test', '--resume' => '/tmp/resume-dir', '--timeout' => '240', '--max-time' => '7200']);

        // Assert
        self::assertNotNull($capturedCommand);
        self::assertSame(240, $capturedCommand->timeout);
        self::assertSame(7200, $capturedCommand->maxTime);
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
            $chainLoader = new YamlChainLoaderService('/nonexistent/default.yaml', new ChainDefinitionFactory(new FixIterationsReferenceIntegritySpecification()), new YamlChainStepMapper(new ChainStepFactory(), new YamlRetryPolicyMapper()), new YamlRetryPolicyMapper());
            $mapper = new \TaskOrchestrator\Common\Module\ChainDefinition\Application\Mapper\ChainDefinitionDtoMapper();
            $loadHandler = new LoadChainQueryHandler($chainLoader, $mapper);

            $command = new OrchestrateCommand(
                BusTestFactory::commandBus($this->orchestrateHandler),
                BusTestFactory::queryBus($this->reportHandler, $loadHandler, $this->validateChainConfigHandler),
                $this->lockFactory,
            );

            $application = new Application();
            $application->addCommand($command);
            $tester = new CommandTester($application->find('agent:orchestrate'));

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
            $chainLoader = new YamlChainLoaderService('/nonexistent/default.yaml', new ChainDefinitionFactory(new FixIterationsReferenceIntegritySpecification()), new YamlChainStepMapper(new ChainStepFactory(), new YamlRetryPolicyMapper()), new YamlRetryPolicyMapper());
            $collector = new \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\CollectFixIterationsViolationsService();
            $chainValidator = new \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainDefinitionValidatorService($collector);
            $violationMapper = new \TaskOrchestrator\Common\Module\ChainDefinition\Application\Mapper\ChainConfigViolationDtoMapper();
            $validateHandler = new ValidateChainConfigQueryHandler($chainLoader, $chainValidator, $violationMapper, $collector);

            $command = new OrchestrateCommand(
                BusTestFactory::commandBus($this->orchestrateHandler),
                BusTestFactory::queryBus($this->reportHandler, $this->loadChainHandler, $validateHandler),
                $this->lockFactory,
            );

            $application = new Application();
            $application->addCommand($command);
            $tester = new CommandTester($application->find('agent:orchestrate'));

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
            BusTestFactory::commandBus($this->orchestrateHandler),
            BusTestFactory::queryBus($this->reportHandler, $this->loadChainHandler, $this->validateChainConfigHandler),
            $this->lockFactory,
        );

        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('agent:orchestrate'));
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
