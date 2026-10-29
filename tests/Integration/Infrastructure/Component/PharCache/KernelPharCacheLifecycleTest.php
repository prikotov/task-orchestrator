<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Infrastructure\Component\PharCache;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\PharCache\CacheRootOwnerMarker;
use TaskOrchestrator\Common\Kernel;




/**
 * Интеграционные тесты жизненного цикла кеш-корня PHAR на уровне ядра
 * ({@see Kernel::boot()}): связка Kernel ↔ {@see \TaskOrchestrator\Common\Component\PharCache\CacheRootOwner}
 * ↔ {@see \TaskOrchestrator\Common\Component\PharCache\CacheRootGarbageCollector}.
 *
 * Покрывает контракт FR-4, недостижимый на уровне чистого компонента:
 *  - вне PHAR-режима захват и очистка — no-op (базовый каталог не создаётся);
 *  - boot в PHAR-режиме удаляет подтверждённо устаревший корень прежнего
 *    расположения и удерживает текущий корень с валидным маркером;
 *  - сбой очистки НЕ прерывает загрузку ядра (exit code команды не меняется —
 *    warning-семантика) и выдаёт безопасную диагностику без раскрытия путей.
 *
 * Физический путь PHAR и базовый каталог инъектируются через protected-швы
 * resolvePharPath()/pharCacheBaseDir(): тесты работают в изолированном
 * временном каталоге и не выполняют очистку в реальном `<sys_tmp>/task-orchestrator`,
 * где лежат корни действующих PHAR-запусков.
 */
#[CoversClass(Kernel::class)]
final class KernelPharCacheLifecycleTest extends TestCase
{
    private const string FAKE_PHAR_PATH = '/tools/task-orchestrator.phar';

    private string $isolatedTemp;

    #[Override]
    protected function setUp(): void
    {
        $this->isolatedTemp = sys_get_temp_dir() . '/to-kernel-phar-cache-' . bin2hex(random_bytes(6));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->forceRemoveTree($this->isolatedTemp);
    }

    #[Test]
    public function bootOutsidePharModeDoesNotTouchCacheRootBase(): void
    {
        // Вне PHAR (resolvePharPath() = null) захват и очистка — no-op:
        // базовый каталог кеш-корней не создаётся, предупреждений нет.
        $kernel = $this->createKernel(projectRoot: $this->packageRoot(), pharPath: null);

        $kernel->boot();

        try {
            self::assertDirectoryDoesNotExist($this->baseDir());
            self::assertSame([], $kernel->cleanupWarnings);
        } finally {
            $kernel->shutdown();
        }
    }

    #[Test]
    public function bootInPharModeRemovesStaleRootAndKeepsCurrentRootMarked(): void
    {
        // Перемещение PHAR A → B: корень A (валидный маркер прежнего
        // расположения, flock свободен — прежний процесс завершился) удаляется
        // автоматически при boot из нового расположения; текущий корень B
        // материализуется с валидным маркером владения.
        $projectRoot = $this->packageRoot();
        $staleRootName = $this->createStaleRoot();
        $kernel = $this->createKernel(projectRoot: $projectRoot, pharPath: self::FAKE_PHAR_PATH);
        $currentRootName = hash('xxh64', $projectRoot . "\0" . self::FAKE_PHAR_PATH);

        $kernel->boot();

        try {
            self::assertDirectoryDoesNotExist($this->baseDir() . '/' . $staleRootName);
            self::assertDirectoryExists($this->baseDir() . '/' . $currentRootName);

            $markerPayload = (string) file_get_contents(
                $this->baseDir() . '/' . $currentRootName . '/' . CacheRootOwnerMarker::FILENAME,
            );
            self::assertSame($currentRootName, CacheRootOwnerMarker::decode($markerPayload));

            // Контейнер собран и работоспособен (boot не прерван очисткой).
            self::assertSame(
                $projectRoot,
                $kernel->getContainer()->getParameter('task_orchestrator.package_dir'),
            );
            self::assertSame([], $kernel->cleanupWarnings);
        } finally {
            $kernel->shutdown();
        }
    }

    #[Test]
    public function cleanupFailureEmitsSafeWarningWithoutBreakingBoot(): void
    {
        // Сбой очистки (неудаляемый устаревший корень) НЕ меняет exit code
        // команды: boot() завершается успешно, сбой — предупреждение в STDERR
        // (warning-семантика контракта). Диагностика не раскрывает содержимое
        // кеша: ни базового каталога, ни имён корней в сообщении нет.
        $projectRoot = $this->packageRoot();
        $stuckRootName = $this->createUnremovableStaleRoot();
        $kernel = $this->createKernel(projectRoot: $projectRoot, pharPath: self::FAKE_PHAR_PATH);

        $kernel->boot();

        try {
            // Boot не бросил исключений — команда продолжит выполнение с кодом 0.
            self::assertSame(
                $projectRoot,
                $kernel->getContainer()->getParameter('task_orchestrator.package_dir'),
            );

            self::assertCount(1, $kernel->cleanupWarnings);
            self::assertStringContainsString('не удалось полностью очистить', $kernel->cleanupWarnings[0]);
            self::assertStringNotContainsString($this->baseDir(), $kernel->cleanupWarnings[0]);
            self::assertStringNotContainsString($stuckRootName, $kernel->cleanupWarnings[0]);

            // Неудаляемый корень сохранён: перебор не прерван, повторная попытка —
            // при следующем запуске.
            self::assertDirectoryExists($this->baseDir() . '/' . $stuckRootName);
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * Ядро с инъекцией PHAR-контекста и изолированным базовым каталогом
     * кеш-корней (protected-швы resolvePharPath()/pharCacheBaseDir());
     * диагностика сбоя очистки перехватывается в cleanupWarnings.
     */
    private function createKernel(string $projectRoot, ?string $pharPath): Kernel
    {
        $baseDir = $this->baseDir();

        return new class ('prod', false, $projectRoot, $pharPath, $baseDir) extends Kernel {
            /**
             * @param string      $environment      имя окружения
             * @param bool        $debug            режим отладки
             * @param string      $projectRoot      каталог host-проекта
             * @param string|null $injectedPharPath физический путь PHAR (null — вне PHAR)
             * @param string      $injectedBaseDir  изолированный базовый каталог кеш-корней
             */
            public function __construct(
                string $environment,
                bool $debug,
                string $projectRoot,
                private readonly ?string $injectedPharPath,
                private readonly string $injectedBaseDir,
            ) {
                parent::__construct($environment, $debug, $projectRoot);
            }

            /** Перехваченные предупреждения о сбое очистки (вместо STDERR). */
            public array $cleanupWarnings = [];

            #[Override]
            protected function resolvePharPath(): ?string
            {
                return $this->injectedPharPath;
            }

            #[Override]
            protected function pharCacheBaseDir(): string
            {
                return $this->injectedBaseDir;
            }

            #[Override]
            protected function reportCacheRootCleanupFailure(string $message): void
            {
                $this->cleanupWarnings[] = $message;
            }
        };
    }

    private function packageRoot(): string
    {
        return dirname(__DIR__, 5);
    }

    private function baseDir(): string
    {
        return $this->isolatedTemp . '/task-orchestrator';
    }

    /**
     * Создаёт устаревший корень с валидным маркером (flock свободен).
     */
    private function createStaleRoot(): string
    {
        $rootName = bin2hex(random_bytes(8));
        $rootDir = $this->baseDir() . '/' . $rootName;
        mkdir($rootDir . '/var/cache', 0700, true);
        file_put_contents($rootDir . '/var/cache/container.php', '<?php echo "stale";');
        file_put_contents(
            $rootDir . '/' . CacheRootOwnerMarker::FILENAME,
            CacheRootOwnerMarker::encode($rootName),
        );

        return $rootName;
    }

    /**
     * Создаёт устаревший корень, неудаляемый для текущего пользователя:
     * вложенный каталог без права записи (0555) блокирует unlink файла.
     * Сам корень и маркер остаются доступными: mkdir без флага recursive,
     * иначе 0555 применился бы ко всему пути и маркер не записался бы.
     */
    private function createUnremovableStaleRoot(): string
    {
        $rootName = bin2hex(random_bytes(8));
        $rootDir = $this->baseDir() . '/' . $rootName;
        mkdir($rootDir, 0700, true);
        mkdir($rootDir . '/locked', 0700);
        file_put_contents($rootDir . '/locked/tail.php', 'unremovable');
        chmod($rootDir . '/locked', 0555);
        file_put_contents(
            $rootDir . '/' . CacheRootOwnerMarker::FILENAME,
            CacheRootOwnerMarker::encode($rootName),
        );

        return $rootName;
    }

    /**
     * Рекурсивная очистка изолированного временного каталога с восстановлением
     * прав (неудаляемые корни теста содержат каталоги 0555).
     */
    private function forceRemoveTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_link($path)) {
                unlink($path);
                continue;
            }

            if (is_dir($path)) {
                @chmod($path, 0700);
                $this->forceRemoveTree($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }
}
