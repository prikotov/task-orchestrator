<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\PharRuntime;

use InvalidArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\PharRuntime\PharRuntimePathsProviderInterface;
use TaskOrchestrator\Common\Component\PharRuntime\TaskOrchestratorPharEnvVarProcessor;

/**
 * Заглушка провайдера путей запущенного кода: фиксирует значения, которые
 * в production отдает ядро ({@see \TaskOrchestrator\Common\Kernel}).
 */
final class StubPharRuntimePathsProvider implements PharRuntimePathsProviderInterface
{
    public function __construct(
        private readonly string $packageDir = '/fixed/package-dir',
        private readonly ?string $pharPath = null,
    ) {
    }

    #[Override]
    public function packageDir(): string
    {
        return $this->packageDir;
    }

    #[Override]
    public function runningPharPath(): ?string
    {
        return $this->pharPath;
    }
}

/**
 * Unit-тест env-процессора runtime phar-зависимых путей ядра
 * {@see TaskOrchestratorPharEnvVarProcessor} (prefix `task_orchestrator_phar`).
 *
 * Закрепляет контракт процессора:
 *  - дискриминатор маршрутизирует к соответствующему методу провайдера путей
 *    запущенного кода (package_dir → packageDir(), phar_path → runningPharPath());
 *  - имя в плейсхолдере — дискриминатор ресурса, а НЕ env-переменная: $getEnv
 *    не вызывается, значение из окружения не читается, задать ресурс через env
 *    нельзя;
 *  - null (код исполняется не из архива) передаётся как есть — параметр
 *    task_orchestrator.phar_path вне PHAR остаётся null;
 *  - неизвестный дискриминатор — явное исключение конфигурации;
 *  - getProvidedTypes() объявляет ровно один префикс с типом string.
 */
#[CoversClass(TaskOrchestratorPharEnvVarProcessor::class)]
final class TaskOrchestratorPharEnvVarProcessorTest extends TestCase
{
    /** $getEnv, падение которого доказывает: процессор не читает окружение. */
    private const string FORBIDDEN_GET_ENV = 'self:forbidden-get-env-closure-invoked';

    #[Test]
    public function packageDirDiscriminatorDelegatesToProvider(): void
    {
        $processor = new TaskOrchestratorPharEnvVarProcessor(
            new StubPharRuntimePathsProvider(packageDir: '/phar/package'),
        );

        self::assertSame(
            '/phar/package',
            $this->resolve($processor, TaskOrchestratorPharEnvVarProcessor::RESOURCE_PACKAGE_DIR),
        );
    }

    #[Test]
    public function pharPathDiscriminatorDelegatesToProvider(): void
    {
        $processor = new TaskOrchestratorPharEnvVarProcessor(
            new StubPharRuntimePathsProvider(pharPath: '/tools/task-orchestrator.phar'),
        );

        self::assertSame(
            '/tools/task-orchestrator.phar',
            $this->resolve($processor, TaskOrchestratorPharEnvVarProcessor::RESOURCE_PHAR_PATH),
        );
    }

    #[Test]
    public function pharPathOutsidePharStaysNull(): void
    {
        // Контракт параметра task_orchestrator.phar_path вне PHAR: null (без
        // «резервного» подстановочного значения). Провайдер возвращает null —
        // процессор передаёт его как есть, без преобразования в строку.
        $processor = new TaskOrchestratorPharEnvVarProcessor(new StubPharRuntimePathsProvider());

        self::assertNull(
            $this->resolve($processor, TaskOrchestratorPharEnvVarProcessor::RESOURCE_PHAR_PATH),
        );
    }

    #[Test]
    public function getEnvClosureIsNeverInvoked(): void
    {
        // Имя в плейсхолдере — дискриминатор ресурса, а не env-переменная:
        // процессор не делегирует чтение окружения (замыкание $getEnv не
        // вызывается ни для одного допустимого дискриминатора).
        $processor = new TaskOrchestratorPharEnvVarProcessor(
            new StubPharRuntimePathsProvider(packageDir: '/phar/package', pharPath: '/tools/x.phar'),
        );

        self::assertSame('/phar/package', $this->resolve($processor, TaskOrchestratorPharEnvVarProcessor::RESOURCE_PACKAGE_DIR));
        self::assertSame('/tools/x.phar', $this->resolve($processor, TaskOrchestratorPharEnvVarProcessor::RESOURCE_PHAR_PATH));
    }

    #[Test]
    public function unknownDiscriminatorThrowsExplicitException(): void
    {
        $processor = new TaskOrchestratorPharEnvVarProcessor(new StubPharRuntimePathsProvider());

        try {
            $this->resolve($processor, 'unknown_resource');
            self::fail('Ожидалось InvalidArgumentException для неизвестного дискриминатора ресурса.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('unknown_resource', $e->getMessage());
            self::assertStringContainsString(TaskOrchestratorPharEnvVarProcessor::ENV_PREFIX, $e->getMessage());
        }
    }

    #[Test]
    public function providedTypesDeclareOnlyOwnPrefix(): void
    {
        self::assertSame(
            [TaskOrchestratorPharEnvVarProcessor::ENV_PREFIX => 'string'],
            TaskOrchestratorPharEnvVarProcessor::getProvidedTypes(),
        );
    }

    /**
     * Разрешение дискриминатора через публичный контракт EnvVarProcessorInterface
     * с запрещающим замыканием $getEnv.
     */
    private function resolve(TaskOrchestratorPharEnvVarProcessor $processor, string $name): mixed
    {
        return $processor->getEnv(
            TaskOrchestratorPharEnvVarProcessor::ENV_PREFIX,
            $name,
            static fn (string $name): string => throw new LogicException(self::FORBIDDEN_GET_ENV),
        );
    }
}
