<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\AgentRole\Infrastructure\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\RoleFileNotFoundException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleNameVo;
use TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service\FilesystemLocateRoleFileService;

/**
 * Unit-тест приоритета кандидатов {@see FilesystemLocateRoleFileService}:
 *   `<role>.<locale>.md` → `<role>.md` → glob `<role>.*.md`.
 *
 * Файловая система эмулируется временным каталогом (как YamlChainLoaderTest):
 * реально вызываются is_file/realpath/glob — без mock'ов, что проверяет
 * фактическое поведение локатора.
 */
#[CoversClass(FilesystemLocateRoleFileService::class)]
final class FilesystemLocateRoleFileServiceTest extends TestCase
{
    private string $rolesDir;

    #[\Override]
    protected function setUp(): void
    {
        $this->rolesDir = sys_get_temp_dir() . '/to_roles_' . uniqid('', true);
        mkdir($this->rolesDir, 0777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->rolesDir);
    }

    #[Test]
    public function localeRoleFileIsPreferredWhenPresent(): void
    {
        // Arrange: есть и .ru.md, и .md — должна победить локаль ru.
        $ru = $this->writeRole('hero.ru.md');
        $this->writeRole('hero.md');

        $locator = new FilesystemLocateRoleFileService($this->rolesDir, 'ru');

        // Act
        $located = $locator->locate(RoleNameVo::createFromName('hero'));

        // Assert
        self::assertSame($ru, $located);
    }

    #[Test]
    public function neutralRoleFileIsUsedWhenLocaleFileAbsent(): void
    {
        // Arrange: локаль en, есть только нейтральный .md.
        $neutral = $this->writeRole('hero.md');

        $locator = new FilesystemLocateRoleFileService($this->rolesDir, 'en');

        // Act
        $located = $locator->locate(RoleNameVo::createFromName('hero'));

        // Assert
        self::assertSame($neutral, $located);
    }

    #[Test]
    public function globFallbackPicksLocalizedFileWhenNoExactMatch(): void
    {
        // Arrange: локаль en, нет .en.md и нет .md — единственный кандидат даёт
        // glob-ветка: первый найденный <role>.*.md.
        $ru = $this->writeRole('hero.ru.md');

        $locator = new FilesystemLocateRoleFileService($this->rolesDir, 'en');

        // Act
        $located = $locator->locate(RoleNameVo::createFromName('hero'));

        // Assert: fallback на единственный доступный перевод.
        self::assertSame($ru, $located);
    }

    #[Test]
    public function localeFileWinsOverGlobMatches(): void
    {
        // Arrange: локаль en, есть .en.md + .ru.md + .md — должна победить .en.md.
        $this->writeRole('hero.ru.md');
        $this->writeRole('hero.md');
        $en = $this->writeRole('hero.en.md');

        $locator = new FilesystemLocateRoleFileService($this->rolesDir, 'en');

        // Act
        $located = $locator->locate(RoleNameVo::createFromName('hero'));

        // Assert
        self::assertSame($en, $located);
    }

    #[Test]
    public function localeIsNormalizedToLowerCase(): void
    {
        // Arrange: локаль в верхнем регистре 'RU' нормализуется → находит .ru.md.
        $ru = $this->writeRole('hero.ru.md');

        $locator = new FilesystemLocateRoleFileService($this->rolesDir, 'RU');

        // Act
        $located = $locator->locate(RoleNameVo::createFromName('hero'));

        // Assert
        self::assertSame($ru, $located);
    }

    #[Test]
    public function locateThrowsWhenNoCandidateExists(): void
    {
        // Arrange
        $locator = new FilesystemLocateRoleFileService($this->rolesDir, 'ru');

        // Assert
        $this->expectException(RoleFileNotFoundException::class);
        $this->expectExceptionMessage('Role "ghost" file not found');

        // Act
        $locator->locate(RoleNameVo::createFromName('ghost'));
    }

    /**
     * Создаёт файл роли и возвращает его canonical realpath (как возвращает locate).
     */
    private function writeRole(string $fileName): string
    {
        $path = $this->rolesDir . '/' . $fileName;
        file_put_contents($path, '---' . "\n" . 'role: stub' . "\n" . '---' . "\n" . '# stub');

        $real = realpath($path);
        \assert($real !== false);

        return $real;
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
