<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Infrastructure\Component\PharCache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\PharCache\CacheRootCleanupReport;
use TaskOrchestrator\Common\Component\PharCache\CacheRootGarbageCollector;
use TaskOrchestrator\Common\Component\PharCache\CacheRootOwnerMarker;
use TaskOrchestrator\Common\Component\PharCache\CacheRootOwnershipLock;


/**
 * Интеграционная матрица диспетчера очистки кеш-корней PHAR
 * ({@see CacheRootGarbageCollector}) на реальной файловой системе.
 *
 * Покрывает контракт FR-4: текущий корень / активный корень (чужой flock) /
 * устаревший корень (валидный маркер, свободный flock) / неизвестный каталог
 * (без маркера, в том числе legacy) / симлинковый узел / идемпотентный повтор,
 * а также негативные ветви: чужой владелец корня, чужой базовый каталог
 * (в том числе с готовыми правами 0700), недоступность POSIX, незащищённый
 * базовый каталог, сбой удаления (не прерывает перебор, фиксируется в отчёте).
 *
 * Тесты наследуют TestCase (а не KernelTestCase): предмет — файловая логика
 * компонента вне контейнера; базовый каталог передаётся аргументом, поэтому
 * каждый сценарий изолирован в собственном каталоге системного temp.
 */
#[CoversClass(CacheRootGarbageCollector::class)]
#[CoversClass(CacheRootCleanupReport::class)]
final class CacheRootGarbageCollectorTest extends TestCase
{
    private string $baseDir;

    #[\Override]
    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/to-phar-gc-' . bin2hex(random_bytes(6)) . '/task-orchestrator';
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeTree(dirname($this->baseDir));
    }

    #[Test]
    public function staleRootWithValidMarkerIsRemovedRecursively(): void
    {
        // Устаревший корень: маркер валиден (self-hash = имя каталога), flock
        // свободен — процесс прежнего расположения давно завершился.
        $rootName = $this->createStaleRoot(nestedFiles: ['var/cache/container.php']);

        $report = $this->collect(currentRootName: 'not-present-current-root');

        self::assertSame(1, $report->removedRoots);
        self::assertFalse($report->hasFailures());
        self::assertDirectoryDoesNotExist($this->baseDir . '/' . $rootName);
    }

    #[Test]
    public function currentRootIsAlwaysExcludedFromCleanup(): void
    {
        $currentRootName = $this->createStaleRoot(nestedFiles: ['var/cache/container.php']);
        $collector = new CacheRootGarbageCollector();

        $report = $collector->collect($this->baseDir, $currentRootName);

        self::assertSame(1, $report->examinedEntries);
        self::assertSame(0, $report->removedRoots);
        self::assertDirectoryExists($this->baseDir . '/' . $currentRootName);
        self::assertFileExists($this->baseDir . '/' . $currentRootName . '/var/cache/container.php');
    }

    #[Test]
    public function activeRootHoldingFlockIsPreservedUntilLockRelease(): void
    {
        // Активный корень: чужой процесс держит flock (здесь — тестовый
        // "процесс" через тот же контракт CacheRootOwnershipLock).
        $rootName = $this->createStaleRoot();
        $activeLock = $this->holdLock($rootName);

        $report = $this->collect('not-present-current-root');

        self::assertSame(1, $report->activePreserved);
        self::assertSame(0, $report->removedRoots);
        self::assertDirectoryExists($this->baseDir . '/' . $rootName);

        // Процесс завершился: блокировка освобождена — следующий проход удаляет корень.
        $activeLock->release();

        $nextReport = $this->collect('not-present-current-root');

        self::assertSame(1, $nextReport->removedRoots);
        self::assertDirectoryDoesNotExist($this->baseDir . '/' . $rootName);
    }

    #[Test]
    public function legacyRootWithoutMarkerIsPreserved(): void
    {
        // Legacy-корень (создан до введения маркера) и незавершённый корень:
        // без маркера владение недоказуемо — сохраняются.
        $legacyRootName = bin2hex(random_bytes(8));
        mkdir($this->baseDir . '/' . $legacyRootName, 0700, true);

        $report = $this->collect('not-present-current-root');

        self::assertSame(1, $report->unknownPreserved);
        self::assertSame(0, $report->removedRoots);
        self::assertDirectoryExists($this->baseDir . '/' . $legacyRootName);
    }

    #[Test]
    public function corruptedMarkerRootIsPreserved(): void
    {
        // Повреждённый маркер (обрыв записи): корень неоднозначен — сохраняется.
        $rootName = bin2hex(random_bytes(8));
        mkdir($this->baseDir . '/' . $rootName, 0700, true);
        file_put_contents($this->baseDir . '/' . $rootName . '/' . CacheRootOwnerMarker::FILENAME, '{"schema":1,"ro');

        $report = $this->collect('not-present-current-root');

        self::assertSame(1, $report->unknownPreserved);
        self::assertDirectoryExists($this->baseDir . '/' . $rootName);
    }

    #[Test]
    public function markerHashMismatchRootIsPreserved(): void
    {
        // Расхождение self-hash маркера и имени каталога — чужой/неизвестный корень.
        $rootName = bin2hex(random_bytes(8));
        mkdir($this->baseDir . '/' . $rootName, 0700, true);
        file_put_contents(
            $this->baseDir . '/' . $rootName . '/' . CacheRootOwnerMarker::FILENAME,
            CacheRootOwnerMarker::encode('another-root-hash'),
        );

        $report = $this->collect('not-present-current-root');

        self::assertSame(1, $report->unknownPreserved);
        self::assertDirectoryExists($this->baseDir . '/' . $rootName);
    }

    #[Test]
    public function foreignOwnedDirectoryIsPreserved(): void
    {
        // Враждебный системный temp: каталог чужого владельца сохраняется.
        // Владелец имитируется швом resolveOwnerUid (фактический владелец узлов —
        // текущий пользователь; зафиксированный uid ему не принадлежит). Шов
        // осведомлён об узле: базовый каталог остаётся «своим» (иначе гейт
        // пропустил бы очистку до перебора узлов), чужим имитируется корень.
        $rootName = $this->createStaleRoot();
        $baseDir = $this->baseDir;
        $collector = new class($baseDir) extends CacheRootGarbageCollector {
            public function __construct(private readonly string $testBaseDir)
            {
            }

            #[\Override]
            protected function resolveOwnerUid(?string $path = null): ?int
            {
                if ($path === null || $path === $this->testBaseDir) {
                    return function_exists('posix_getuid') ? posix_getuid() : null;
                }

                return 2147483000;
            }
        };

        $report = $collector->collect($this->baseDir, 'not-present-current-root');

        self::assertSame(1, $report->foreignPreserved);
        self::assertSame(0, $report->removedRoots);
        self::assertDirectoryExists($this->baseDir . '/' . $rootName);
    }

    #[Test]
    public function cleanupIsSkippedWhenPosixFunctionsUnavailable(): void
    {
        // Ограничение Windows: POSIX-функции недоступны — очистка не выполняется.
        $rootName = $this->createStaleRoot();
        $collector = new class extends CacheRootGarbageCollector {
            #[\Override]
            protected function resolveOwnerUid(?string $path = null): ?int
            {
                return null;
            }
        };

        $report = $collector->collect($this->baseDir, 'not-present-current-root');

        self::assertFalse($report->performed);
        self::assertSame(CacheRootCleanupReport::SKIP_REASON_POSIX_UNAVAILABLE, $report->skipReason);
        self::assertDirectoryExists($this->baseDir . '/' . $rootName);
    }

    #[Test]
    public function symlinkNodeIsUnlinkedWithoutTraversal(): void
    {
        // Симлинковый узел удаляется unlink самого узла; цель симлинка не затрагивается.
        $rootName = $this->createStaleRoot();
        $targetDir = dirname($this->baseDir) . '/gc-symlink-target-' . bin2hex(random_bytes(4));
        mkdir($targetDir . '/inner', 0777, true);
        file_put_contents($targetDir . '/inner/file.txt', 'data');
        symlink($targetDir, $this->baseDir . '/linked-root');

        $report = $this->collect('not-present-current-root');

        self::assertSame(1, $report->removedSymlinks);
        self::assertFalse(is_link($this->baseDir . '/linked-root'));
        self::assertFileExists($targetDir . '/inner/file.txt', 'цель симлинка не должна затрагиваться');
        // Попутный устаревший корень удаляется по обычной политике (валидный
        // маркер, свободный flock); предмет теста — поведение симлинка.
        self::assertSame(1, $report->removedRoots);
        self::assertDirectoryDoesNotExist($this->baseDir . '/' . $rootName);
    }

    #[Test]
    public function plainFileEntryIsPreserved(): void
    {
        // Файл в базовом каталоге — не кеш-корень: сохраняется.
        $filePath = $this->baseDir . '/stray-file.txt';
        mkdir($this->baseDir, 0700, true);
        file_put_contents($filePath, 'data');

        $report = $this->collect('not-present-current-root');

        self::assertSame(1, $report->unknownPreserved);
        self::assertFileExists($filePath);
    }

    #[Test]
    public function insecureBaseDirectorySkipsCleanup(): void
    {
        // Базовый каталог — симлинк: traverse запрещён, очистка не выполняется.
        $rootName = $this->createStaleRoot();
        $elsewhere = dirname($this->baseDir) . '/gc-elsewhere-' . bin2hex(random_bytes(4));
        mkdir($elsewhere, 0700, true);

        try {
            $report = (new CacheRootGarbageCollector())->collect($elsewhere, 'not-present-current-root');

            // Заменяем базовый каталог симлинком ПОСЛЕ контрольного прохода.
            rmdir($elsewhere);
            symlink($this->baseDir, $elsewhere);

            $symlinkReport = (new CacheRootGarbageCollector())->collect($elsewhere, 'not-present-current-root');

            self::assertTrue($report->performed);
            self::assertFalse($symlinkReport->performed);
            self::assertSame(CacheRootCleanupReport::SKIP_REASON_BASE_DIR_INSECURE, $symlinkReport->skipReason);
            self::assertDirectoryExists($this->baseDir . '/' . $rootName);
        } finally {
            if (is_link($elsewhere)) {
                unlink($elsewhere);
            } elseif (is_dir($elsewhere)) {
                rmdir($elsewhere);
            }
        }
    }

    #[Test]
    public function baseDirectoryPermissionsAreCorrectedToZeroSevenZeroZero(): void
    {
        // Права mkdir искажаются umask: диспетчер исправляет права базового
        // каталога на 0700 и выполняет очистку.
        $rootName = $this->createStaleRoot();
        chmod($this->baseDir, 0755);
        self::assertSame(0o755, fileperms($this->baseDir) & 0o777);

        $report = $this->collect('not-present-current-root');

        self::assertTrue($report->performed);
        self::assertSame(0o700, fileperms($this->baseDir) & 0o777);
        self::assertSame(1, $report->removedRoots);
        self::assertDirectoryDoesNotExist($this->baseDir . '/' . $rootName);
    }

    #[Test]
    public function foreignOwnedBaseDirectoryWithPrivateModeIsSkipped(): void
    {
        // Чужой базовый каталог с правами 0700: гейт обязан отклонить его без
        // единого изменения узла (ни chmod, ни чтение). Владелец имитируется
        // швом resolveOwnerUid, как в foreignOwnedDirectoryIsPreserved;
        // готовые права 0700 не дают скидки — попытка chmod выполняется
        // безусловно, владение подтверждается сверкой fileowner.
        $rootName = $this->createStaleRoot();
        chmod($this->baseDir, 0o700);
        self::assertSame(0o700, fileperms($this->baseDir) & 0o777);

        $collector = new class extends CacheRootGarbageCollector {
            #[\Override]
            protected function resolveOwnerUid(?string $path = null): ?int
            {
                return 2147483000;
            }
        };

        $report = $collector->collect($this->baseDir, 'not-present-current-root');

        self::assertFalse($report->performed);
        self::assertSame(CacheRootCleanupReport::SKIP_REASON_BASE_DIR_INSECURE, $report->skipReason);
        self::assertDirectoryExists($this->baseDir . '/' . $rootName);
    }

    #[Test]
    public function repeatedCleanupIsIdempotent(): void
    {
        // Идемпотентный повтор: после успешной очистки повторный проход не падает,
        // не удаляет лишнего и не фиксирует сбоев.
        $rootName = $this->createStaleRoot();

        $first = $this->collect('not-present-current-root');
        $second = $this->collect('not-present-current-root');
        $third = $this->collect('not-present-current-root');

        self::assertSame(1, $first->removedRoots);
        self::assertSame(0, $second->removedRoots);
        self::assertSame(0, $second->examinedEntries);
        self::assertFalse($second->hasFailures());
        self::assertSame(0, $third->removedRoots);
        self::assertDirectoryDoesNotExist($this->baseDir . '/' . $rootName);
    }

    #[Test]
    public function unremovableRootIsCountedAsFailureWithoutInterruptingSiblings(): void
    {
        // Сценарий EACCES (каталог 0555) неприменим под root: uid 0 игнорирует
        // запрет записи, неудаляемый корень удалился бы — на root-раннере CI
        // тест пропускается.
        if (!function_exists('posix_getuid') || posix_getuid() === 0) {
            self::markTestSkipped('Сценарий EACCES (каталог 0555) неприменим без POSIX или под root (uid 0).');
        }
        self::assertNotSame(0, posix_getuid());

        // Сбой удаления: вложенный каталог 0555 блокирует unlink файла; перебор
        // не прерывается, соседний устаревший корень удаляется, сбой — в отчёте
        // (warning-семантику обеспечивает Kernel).
        $stuckRootName = $this->createStuckRemovalRoot();
        $freshRootName = $this->createStaleRoot();

        $report = $this->collect('not-present-current-root');

        self::assertSame(1, $report->failures);
        self::assertSame(1, $report->removedRoots);
        self::assertDirectoryExists($this->baseDir . '/' . $stuckRootName);
        self::assertDirectoryDoesNotExist($this->baseDir . '/' . $freshRootName);

        // Восстановление для tearDown: снимаем запрет записи.
        chmod($this->baseDir . '/' . $stuckRootName . '/locked', 0700);
    }

    /**
     * Создаёт устаревший корень с валидным маркером и вложенным содержимым.
     *
     * @param list<string> $nestedFiles относительные пути файлов внутри корня
     */
    private function createStaleRoot(array $nestedFiles = []): string
    {
        $rootName = bin2hex(random_bytes(8));
        $rootDir = $this->baseDir . '/' . $rootName;
        mkdir($rootDir, 0700, true);

        foreach ($nestedFiles as $nestedFile) {
            $filePath = $rootDir . '/' . $nestedFile;
            mkdir(dirname($filePath), 0755, true);
            file_put_contents($filePath, '<?php echo "cached";');
        }

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
    private function createStuckRemovalRoot(): string
    {
        $rootName = bin2hex(random_bytes(8));
        $rootDir = $this->baseDir . '/' . $rootName;
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
     * Удерживает эксклюзивный flock корня, имитируя активный чужой процесс.
     */
    private function holdLock(string $rootName): CacheRootOwnershipLock
    {
        $lock = CacheRootOwnershipLock::acquireToOwn($this->baseDir . '/' . $rootName);
        self::assertNotNull($lock, 'имитация активного процесса: flock не захватился');

        return $lock;
    }

    private function collect(string $currentRootName): CacheRootCleanupReport
    {
        return (new CacheRootGarbageCollector())->collect($this->baseDir, $currentRootName);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        self::assertNotFalse($items);

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
                chmod($path, 0700);
                $this->removeTree($path);
                continue;
            }

            unlink($path);
        }

        rmdir($dir);
    }
}
