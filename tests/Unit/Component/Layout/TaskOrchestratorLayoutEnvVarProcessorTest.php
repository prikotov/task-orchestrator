<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\Layout\HostPackageLayoutResolver;
use TaskOrchestrator\Common\Component\Layout\LayoutResourceNotFoundException;
use TaskOrchestrator\Common\Component\Layout\TaskOrchestratorLayoutEnvVarProcessor;

/**
 * Unit-тест env-процессора runtime layout
 * {@see TaskOrchestratorLayoutEnvVarProcessor}.
 *
 * Закрепляет контракт процессора:
 *  - конструктор принимает ТОЛЬКО plain-значения (host-корень и package-корень
 *    как строки — в контейнере это plain-параметры %task_orchestrator.base_path%
 *    и %task_orchestrator.package_dir%; %env(...)% в аргументах процессора
 *    запрещён — рекурсия Container::getEnv(), см. PHPDoc класса);
 *  - имя в плейсхолдере — дискриминатор ресурса, а не env-переменная: $getEnv
 *    не вызывается, значение из окружения не читается;
 *  - неизвестный дискриминатор — явное исключение конфигурации;
 *  - нормализация путей (trailing slash) наследуется от чистого резолвера.
 */
#[CoversClass(TaskOrchestratorLayoutEnvVarProcessor::class)]
final class TaskOrchestratorLayoutEnvVarProcessorTest extends TestCase
{
    private string $temp;

    private string $hostRoot;

    private string $packageRoot;

    private TaskOrchestratorLayoutEnvVarProcessor $processor;

    #[\Override]
    protected function setUp(): void
    {
        $this->temp = sys_get_temp_dir() . '/layout-processor-' . bin2hex(random_bytes(6));
        $this->hostRoot = $this->temp . '/host';
        $this->packageRoot = $this->temp . '/package';
        mkdir($this->hostRoot, 0777, true);
        mkdir($this->packageRoot, 0777, true);

        // Контракт plain-параметров: процессор строится из двух строк (в
        // контейнере — plain-параметры), без env-зависимостей.
        $this->processor = new TaskOrchestratorLayoutEnvVarProcessor($this->hostRoot, $this->packageRoot);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->temp);
    }

    #[Test]
    public function discriminatorRoutesToCorrespondingResource(): void
    {
        mkdir($this->packageRoot . '/docs/agents/roles/team', 0777, true);
        mkdir($this->hostRoot . '/docs/agents/skills', 0777, true);
        mkdir($this->packageRoot . '/config', 0777, true);
        file_put_contents($this->packageRoot . '/config/chains.yaml', "chains: []\n");

        self::assertSame(
            $this->packageRoot . '/docs/agents/roles/team',
            $this->resolve(TaskOrchestratorLayoutEnvVarProcessor::RESOURCE_ROLES_DIR),
        );
        self::assertSame(
            $this->hostRoot . '/docs/agents/skills',
            $this->resolve(TaskOrchestratorLayoutEnvVarProcessor::RESOURCE_SKILLS_DIR),
        );
        self::assertSame(
            $this->packageRoot . '/config/chains.yaml',
            $this->resolve(TaskOrchestratorLayoutEnvVarProcessor::RESOURCE_CHAINS_YAML),
        );
    }

    #[Test]
    public function discriminatorIsNotReadFromEnvironment(): void
    {
        // Имя после префикса — константа выбора ресурса: $getEnv не вызывается,
        // поэтому контейнер никогда не потребует несуществующую env-переменную
        // (замыкание бросает при любой попытке обращения).
        mkdir($this->packageRoot . '/docs/agents/roles/team', 0777, true);

        self::assertSame(
            $this->packageRoot . '/docs/agents/roles/team',
            $this->resolve(
                TaskOrchestratorLayoutEnvVarProcessor::RESOURCE_ROLES_DIR,
                static fn (string $name): string => throw new \LogicException(
                    sprintf('Процессор не должен читать env-переменную "%s".', $name),
                ),
            ),
        );
    }

    #[Test]
    public function unknownDiscriminatorThrowsConfigurationException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Неизвестный ресурс layout');

        $this->resolve('unexpected_resource');
    }

    #[Test]
    public function missingBothCandidatesPropagatesExplicitException(): void
    {
        $this->expectException(LayoutResourceNotFoundException::class);
        $this->expectExceptionMessage('Ни host-, ни package-ресурс не найден');

        $this->resolve(TaskOrchestratorLayoutEnvVarProcessor::RESOURCE_SKILLS_DIR);
    }

    #[Test]
    public function plainRootsAreNormalized(): void
    {
        // Нормализация путей наследуется от чистого резолвера
        // (trailing slash корней не даёт двойной разделитель).
        mkdir($this->hostRoot . '/docs/agents/roles/team', 0777, true);

        $processor = new TaskOrchestratorLayoutEnvVarProcessor(
            $this->hostRoot . '/',
            $this->packageRoot . '/',
        );

        self::assertSame(
            $this->hostRoot . '/docs/agents/roles/team',
            $processor->getEnv(
                TaskOrchestratorLayoutEnvVarProcessor::ENV_PREFIX,
                TaskOrchestratorLayoutEnvVarProcessor::RESOURCE_ROLES_DIR,
                static fn (): string => '',
            ),
        );
    }

    #[Test]
    public function providedTypesDeclareProcessorPrefix(): void
    {
        self::assertSame(
            ['task_orchestrator_layout' => 'string'],
            TaskOrchestratorLayoutEnvVarProcessor::getProvidedTypes(),
        );
    }

    #[Test]
    public function resolverContractIsReused(): void
    {
        // Процессор остаётся тонким: фактический выбор выполняет чистый
        // резолвер (сравнение с прямым вызовом на тех же корнях).
        mkdir($this->hostRoot . '/docs/agents/skills', 0777, true);

        $resolver = new HostPackageLayoutResolver($this->hostRoot, $this->packageRoot);

        self::assertSame(
            $resolver->resolveSkillsDir(),
            $this->resolve(TaskOrchestratorLayoutEnvVarProcessor::RESOURCE_SKILLS_DIR),
        );
    }

    /**
     * Прогоняет процессор с getEnv-замыканием, не читающим окружение.
     */
    private function resolve(string $resource, ?\Closure $getEnv = null): string
    {
        $resolved = $this->processor->getEnv(
            TaskOrchestratorLayoutEnvVarProcessor::ENV_PREFIX,
            $resource,
            $getEnv ?? static fn (): string => '',
        );

        self::assertIsString($resolved);

        return $resolved;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
