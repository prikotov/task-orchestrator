<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Console;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Mapper\ChainConfigViolationDtoMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadChain\LoadChainQueryHandler;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\ValidateChainConfig\ValidateChainConfigQueryHandler;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainStepFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainDefinitionValidatorService;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\CollectFixIterationsViolationsService;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlChainStepMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlRetryPolicyMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\YamlChainLoaderService;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Enum\OrchestrateExitCodeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\GenerateReport\GenerateReportQueryHandler;
use TaskOrchestrator\Console\Module\Orchestrator\Command\OrchestrateCommand;
use TaskOrchestrator\Tests\Double\Component\BusTestFactory;

/**
 * Приёмочный integration-тест задачи TASK-fix-fixiterations-config-error-dx.
 *
 * Прогоняет полный путь `agent:orchestrate --validate-config --chain <broken>` через
 * РЕАЛЬНЫЕ слои Domain (factory, collector, validator) + Application (handler, mapper) +
 * Infrastructure (YamlChainLoaderService). До фиксы factory fail-fast падала раньше
 * валидатора → пользователь видел «глухое» generic-сообщение без имени группы/шага
 * (слепая зона №2). После фиксы validate-путь ловит carrier-исключение и выводит
 * detailed-диагностику через единый источник (коллектор).
 */
#[CoversNothing]
final class OrchestrateCommandValidateConfigTest extends TestCase
{
    private string $tempDir;

    #[Override]
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/orch_validate_config_' . uniqid(more_entropy: true);
        mkdir($this->tempDir, 0777, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*') ?: [];
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }
    }

    /**
     * Главный приёмочный сценарий: невалидные fix_iterations (unknown step) в реальном
     * YAML → detailed-сообщение с именем группы и шага вместо «глухого» generic-краша.
     */
    #[Test]
    public function validateConfigWithUnknownFixIterationStepRendersDetailedMessage(): void
    {
        $configPath = $this->tempDir . '/chains.yaml';
        file_put_contents($configPath, <<<'YAML'
chains:
  broken:
    description: "Chain with invalid fix_iterations"
    steps:
      - { type: agent, role: dev, name: step1 }
      - { type: agent, role: qa, name: step2 }
    fix_iterations:
      - group: group1
        steps: [step1, ghost]
        max_iterations: 3
YAML);

        $tester = $this->createCommandTester($configPath);
        $tester->execute([
            'task' => '_',
            '--validate-config' => true,
            '--chain' => 'broken',
            '--config' => $configPath,
        ]);

        $output = $tester->getDisplay();

        self::assertSame(OrchestrateExitCodeEnum::invalidConfig->value, $tester->getStatusCode());
        self::assertStringContainsString('Config validation failed', $output);
        // Detailed-диагностика уровня detailed-валидатора: имя группы + неизвестный шаг.
        self::assertStringContainsString('[fix_iterations]', $output);
        self::assertStringContainsString('fix_iteration group "group1" references unknown step "ghost".', $output);
    }

    /**
     * Сценарий duplicate-membership: шаг в двух группах → detailed-сообщение с шагом и обеими группами.
     */
    #[Test]
    public function validateConfigWithDuplicateFixIterationStepRendersDetailedMessage(): void
    {
        $configPath = $this->tempDir . '/chains.yaml';
        file_put_contents($configPath, <<<'YAML'
chains:
  dup:
    description: "Chain with step in two fix_iteration groups"
    steps:
      - { type: agent, role: dev, name: shared }
      - { type: agent, role: qa, name: only_a }
      - { type: agent, role: ops, name: only_b }
    fix_iterations:
      - group: groupA
        steps: [shared, only_a]
        max_iterations: 3
      - group: groupB
        steps: [shared, only_b]
        max_iterations: 3
YAML);

        $tester = $this->createCommandTester($configPath);
        $tester->execute([
            'task' => '_',
            '--validate-config' => true,
            '--chain' => 'dup',
            '--config' => $configPath,
        ]);

        $output = $tester->getDisplay();

        self::assertSame(OrchestrateExitCodeEnum::invalidConfig->value, $tester->getStatusCode());
        self::assertStringContainsString(
            'fix_iteration step "shared" belongs to multiple groups ("groupA" and "groupB").',
            $output,
        );
    }

    /**
     * Контроль happy-path: валидный конфиг с корректными fix_iterations → success.
     * Гарантирует, что detailed-ветка не маскирует корректные конфиги.
     */
    #[Test]
    public function validateConfigWithValidFixIterationsSucceeds(): void
    {
        $configPath = $this->tempDir . '/chains.yaml';
        file_put_contents($configPath, <<<'YAML'
chains:
  good:
    description: "Chain with valid fix_iterations"
    steps:
      - { type: agent, role: dev, name: implement }
      - { type: agent, role: qa, name: review }
    fix_iterations:
      - group: dev-review
        steps: [implement, review]
        max_iterations: 2
YAML);

        $tester = $this->createCommandTester($configPath);
        $tester->execute([
            'task' => '_',
            '--validate-config' => true,
            '--chain' => 'good',
            '--config' => $configPath,
        ]);

        $output = $tester->getDisplay();

        self::assertSame(OrchestrateExitCodeEnum::success->value, $tester->getStatusCode());
        self::assertStringContainsString('Config is valid', $output);
        self::assertStringContainsString('good', $output);
    }

    /**
     * Собирает OrchestrateCommand с РЕАЛЬНЫМ validate-handler (реальные factory,
     * loader, collector, validator, mapper) и замоканными остальными handler'ами
     * (они не вызываются в пути --validate-config).
     */
    private function createCommandTester(string $configPath): CommandTester
    {
        $collector = new CollectFixIterationsViolationsService();
        $chainLoader = new YamlChainLoaderService(
            $configPath,
            new ChainDefinitionFactory(new FixIterationsReferenceIntegritySpecification()),
            new YamlChainStepMapper(new ChainStepFactory(), new YamlRetryPolicyMapper()),
            new YamlRetryPolicyMapper(),
        );
        $validateHandler = new ValidateChainConfigQueryHandler(
            $chainLoader,
            new ChainDefinitionValidatorService($collector),
            new ChainConfigViolationDtoMapper(),
            $collector,
        );

        $orchestrateHandler = $this->createMock(OrchestrateChainCommandHandler::class);
        $reportHandler = $this->createMock(GenerateReportQueryHandler::class);
        $loadChainHandler = $this->createMock(LoadChainQueryHandler::class);
        $lockFactory = new LockFactory(new FlockStore());

        $command = new OrchestrateCommand(
            BusTestFactory::commandBus($orchestrateHandler),
            BusTestFactory::queryBus($reportHandler, $loadChainHandler, $validateHandler),
            $lockFactory,
        );

        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('agent:orchestrate'));
    }
}
