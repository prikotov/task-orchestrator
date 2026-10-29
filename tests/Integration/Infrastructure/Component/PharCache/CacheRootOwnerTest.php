<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Infrastructure\Component\PharCache;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\PharCache\CacheRootOwner;
use TaskOrchestrator\Common\Component\PharCache\CacheRootOwnerMarker;



/**
 * Интеграционные тесты захвата кеш-корня PHAR во владение ({@see CacheRootOwner}):
 * порядок контракта `mkdir` (`0700`) → `flock` → маркер, эксклюзивность
 * блокировки между запусками, отказ от чужого по владельцу узла.
 *
 * Тесты наследуют TestCase (а не KernelTestCase): предмет — файловая логика
 * компонента вне контейнера; базовый каталог передаётся аргументом.
 */
#[CoversClass(CacheRootOwner::class)]
final class CacheRootOwnerTest extends TestCase
{
    private string $baseDir;

    #[\Override]
    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/to-phar-owner-' . bin2hex(random_bytes(6)) . '/task-orchestrator';
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeTree(dirname($this->baseDir));
    }

    #[Test]
    public function claimCreatesBaseAndRootWithMarkerAndStrictPermissions(): void
    {
        $rootName = $this->rootName();
        $owner = new CacheRootOwner();

        $lock = $owner->claim($this->baseDir, $rootName);

        self::assertNotNull($lock);
        self::assertDirectoryExists($this->baseDir . '/' . $rootName);
        // Права 0700 подтверждены независимо от umask процесса.
        self::assertSame(0o700, fileperms($this->baseDir) & 0o777);
        self::assertSame(0o700, fileperms($this->baseDir . '/' . $rootName) & 0o777);

        $markerPayload = (string) file_get_contents(
            $this->baseDir . '/' . $rootName . '/' . CacheRootOwnerMarker::FILENAME,
        );
        self::assertSame($rootName, CacheRootOwnerMarker::decode($markerPayload));

        $lock->release();
    }

    #[Test]
    public function claimIsExclusiveWhileLockHeldAndRepeatableAfterRelease(): void
    {
        $rootName = $this->rootName();
        $owner = new CacheRootOwner();

        $first = $owner->claim($this->baseDir, $rootName);
        self::assertNotNull($first);

        // Параллельный запуск в тот же корень: flock занят — захват отклонён,
        // маркер не переписывается (порядок контракта — flock → маркер).
        self::assertNull($owner->claim($this->baseDir, $rootName));
        self::assertSame(
            $rootName,
            CacheRootOwnerMarker::decode(
                (string) file_get_contents($this->baseDir . '/' . $rootName . '/' . CacheRootOwnerMarker::FILENAME),
            ),
        );

        // Предыдущий процесс завершился: захват повторяется (идемпотентно).
        $first->release();
        $second = $owner->claim($this->baseDir, $rootName);
        self::assertNotNull($second);
        $second->release();
    }

    #[Test]
    public function claimRefusesNodeOwnedByOtherUser(): void
    {
        // Враждебный системный temp: узел, «чужой» по владельцу (имитация швом
        // resolveOwnerUid), не занимается ни по базовому каталогу, ни по корню.
        $rootName = $this->rootName();
        $owner = new class extends CacheRootOwner {
            #[\Override]
            protected function resolveOwnerUid(): ?int
            {
                return 2147483000;
            }
        };

        self::assertNull($owner->claim($this->baseDir, $rootName));
        self::assertDirectoryDoesNotExist($this->baseDir . '/' . $rootName);
    }

    #[Test]
    public function claimRefusesSymlinkBaseDirectory(): void
    {
        // Базовый каталог — симлинк: владение не захватывается (traverse запрещён).
        $elsewhere = dirname($this->baseDir) . '/owner-elsewhere-' . bin2hex(random_bytes(4));
        mkdir($this->baseDir, 0700, true);
        symlink($this->baseDir, $elsewhere);

        try {
            $owner = new CacheRootOwner();

            self::assertNull($owner->claim($elsewhere, $this->rootName()));
        } finally {
            unlink($elsewhere);
        }
    }

    #[Test]
    public function claimSurvivesPreexistingCorruptedMarker(): void
    {
        // Аварийное завершение при записи маркера: захват восстанавливает валидный
        // маркер (flock свободен, владелец наш — корень наш).
        $rootName = $this->rootName();
        mkdir($this->baseDir . '/' . $rootName, 0700, true);
        file_put_contents($this->baseDir . '/' . $rootName . '/' . CacheRootOwnerMarker::FILENAME, '{"schem');

        $owner = new CacheRootOwner();
        $lock = $owner->claim($this->baseDir, $rootName);

        self::assertNotNull($lock);
        self::assertSame(
            $rootName,
            CacheRootOwnerMarker::decode(
                (string) file_get_contents($this->baseDir . '/' . $rootName . '/' . CacheRootOwnerMarker::FILENAME),
            ),
        );
        $lock->release();
    }

    private function rootName(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            throw new AssertionFailedError('Не удалось прочитать каталог: ' . $dir);
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
                $this->removeTree($path);
                continue;
            }

            unlink($path);
        }

        rmdir($dir);
    }
}
