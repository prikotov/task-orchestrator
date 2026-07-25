<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\ChainExecution\Infrastructure\Service\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Exception\RoleNotFoundException;
use TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\Prompt\RolePromptBuilderService;

/**
 * Unit-тест локаль-зависимого выбора role-файла {@see RolePromptBuilderService}.
 *
 * Fallback-цепочка идентична {@see \TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service\FilesystemLocateRoleFileService}:
 *   `<role>.<locale>.md` → `<role>.md` (нейтральный) → любой `<role>.*.md`.
 *
 * Файловая система эмулируется временным каталогом (как FilesystemLocateRoleFileServiceTest):
 * реально вызываются glob/file_get_contents — без mock'ов, что проверяет фактическое
 * поведение построителя промптов.
 */
#[CoversClass(RolePromptBuilderService::class)]
final class RolePromptBuilderServiceTest extends TestCase
{
    private string $rolesDir;

    private string $basePath;

    #[\Override]
    protected function setUp(): void
    {
        $this->rolesDir = sys_get_temp_dir() . '/to_chain_roles_' . uniqid('', true);
        $this->basePath = sys_get_temp_dir();
        mkdir($this->rolesDir, 0777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->rolesDir);
    }

    #[Test]
    public function getPromptReturnsContentOfLocaleFile(): void
    {
        // Arrange: локаль en, есть .en.md — должна победить локаль.
        $this->writeRole('hero.en.md', "# Hero EN\n\nEnglish prompt.");

        $builder = new RolePromptBuilderService($this->rolesDir, $this->basePath, 'en');

        // Act
        $prompt = $builder->getPrompt('hero');

        // Assert
        self::assertStringContainsString('Hero EN', $prompt);
        self::assertStringContainsString('English prompt.', $prompt);
    }

    #[Test]
    public function localeFileWinsOverNeutralAndOtherTranslations(): void
    {
        // Arrange: локаль ru, есть .ru.md + .en.md + .md — должна победить .ru.md.
        $this->writeRole('hero.en.md', "# Hero EN\n\nen");
        $this->writeRole('hero.md', "# Hero Neutral\n\nneutral");
        $this->writeRole('hero.ru.md', "# Hero RU\n\nru");

        $builder = new RolePromptBuilderService($this->rolesDir, $this->basePath, 'ru');

        // Act
        $prompt = $builder->getPrompt('hero');

        // Assert: выбрана именно ru-версия, а не нейтральная и не en.
        self::assertStringContainsString('Hero RU', $prompt);
        self::assertStringNotContainsString('Hero Neutral', $prompt);
        self::assertStringNotContainsString('Hero EN', $prompt);
    }

    #[Test]
    public function neutralFileUsedWhenLocaleFileAbsent(): void
    {
        // Arrange: локаль zh, нет .zh.md, есть нейтральный .md.
        $this->writeRole('hero.md', "# Hero Neutral\n\nneutral");

        $builder = new RolePromptBuilderService($this->rolesDir, $this->basePath, 'zh');

        // Act
        $prompt = $builder->getPrompt('hero');
        $path = $builder->getPromptFilePath('hero');

        // Assert
        self::assertStringContainsString('Hero Neutral', $prompt);
        self::assertStringEndsWith('hero.md', $path);
    }

    #[Test]
    public function neutralFileWinsOverCompetingTranslationWhenLocaleAbsent(): void
    {
        // Arrange: локаль zh отсутствует, НО есть нейтральный `<role>.md`
        // И конкурирующий перевод `<role>.en.md` (по glob-порядку идёт раньше
        // нейтрального: `hero.en.md` < `hero.md`). Должна победить neutral —
        // это доказывает приоритет нейтрального файла над произвольным переводом
        // в fallback-цепочке (шаг 2 выше шага 3).
        $this->writeRole('hero.en.md', "# Hero EN\n\nen");
        $this->writeRole('hero.md', "# Hero Neutral\n\nneutral");

        $builder = new RolePromptBuilderService($this->rolesDir, $this->basePath, 'zh');

        // Act
        $prompt = $builder->getPrompt('hero');
        $path = $builder->getPromptFilePath('hero');

        // Assert: выбран нейтральный файл, а не первый по сортировке перевод .en.md.
        self::assertStringContainsString('Hero Neutral', $prompt);
        self::assertStringNotContainsString('Hero EN', $prompt);
        self::assertStringEndsWith('hero.md', $path);
    }

    #[Test]
    public function globFallbackPicksFirstAvailableTranslation(): void
    {
        // Arrange: локаль zh, нет .zh.md и нет нейтрального — единственный
        // кандидат даёт fallback-ветка glob: первый найденный перевод (.en.md
        // идёт раньше .ru.md по алфавитной сортировке glob).
        $this->writeRole('hero.ru.md', "# Hero RU\n\nru");
        $this->writeRole('hero.en.md', "# Hero EN\n\nen");

        $builder = new RolePromptBuilderService($this->rolesDir, $this->basePath, 'zh');

        // Act
        $prompt = $builder->getPrompt('hero');
        $path = $builder->getPromptFilePath('hero');

        // Assert: fallback на первый по glob-порядку (.en.md < .ru.md).
        self::assertStringContainsString('Hero EN', $prompt);
        self::assertStringEndsWith('hero.en.md', $path);
    }

    #[Test]
    public function localeIsNormalizedToLowerCase(): void
    {
        // Arrange: локаль 'RU' нормализуется → находит .ru.md.
        $this->writeRole('hero.ru.md', "# Hero RU\n\nru");

        $builder = new RolePromptBuilderService($this->rolesDir, $this->basePath, 'RU');

        // Act
        $prompt = $builder->getPrompt('hero');

        // Assert
        self::assertStringContainsString('Hero RU', $prompt);
    }

    #[Test]
    public function getPromptFilePathReturnsRelativeFromBasePath(): void
    {
        // Arrange: выбранный файл лежит внутри basePath.
        $this->writeRole('hero.ru.md', "# Hero RU\n\nru");

        $builder = new RolePromptBuilderService($this->rolesDir, $this->basePath, 'ru');

        // Act
        $path = $builder->getPromptFilePath('hero');

        // Assert: относительный путь от basePath (без ведущего '/').
        self::assertStringEndsWith('hero.ru.md', $path);
        self::assertFalse(str_starts_with($path, '/'));
    }

    #[Test]
    public function getPromptFilePathThrowsWhenRoleMissing(): void
    {
        // Arrange
        $builder = new RolePromptBuilderService($this->rolesDir, $this->basePath, 'ru');

        // Assert
        $this->expectException(RoleNotFoundException::class);
        $this->expectExceptionMessage('Role "ghost" not found.');

        // Act
        $builder->getPromptFilePath('ghost');
    }

    #[Test]
    public function getAvailableRolesListsAllRolesRegardlessOfLocale(): void
    {
        // Arrange: роли в разных локалях + нейтральная. Список должен включать
        // ВСЕ уникальные роли каталога, а не только файлы под фиксированную локаль.
        $this->writeRole('hero.en.md', "# Hero EN\n\nen");
        $this->writeRole('villain.zh.md', "# Villain ZH\n\nzh");
        $this->writeRole('narrator.md', "# Narrator\n\nneutral");

        $builder = new RolePromptBuilderService($this->rolesDir, $this->basePath, 'ru');

        // Act
        $roles = $builder->getAvailableRoles();

        // Assert
        self::assertSame(
            [
                'hero' => 'Hero EN',
                'narrator' => 'Narrator',
                'villain' => 'Villain ZH',
            ],
            $roles,
        );
    }

    #[Test]
    public function roleExistsReturnsTrueForRoleAvailableViaAnyTranslation(): void
    {
        // Arrange: роль существует только в .en.md, запрашиваем локаль ru.
        $this->writeRole('hero.en.md', "# Hero EN\n\nen");

        $builder = new RolePromptBuilderService($this->rolesDir, $this->basePath, 'ru');

        // Act / Assert
        self::assertTrue($builder->roleExists('hero'));
        self::assertFalse($builder->roleExists('ghost'));
    }

    #[Test]
    public function getAvailableRolesReturnsEmptyWhenDirHasNoRoleFiles(): void
    {
        // Arrange: каталог пуст.
        $builder = new RolePromptBuilderService($this->rolesDir, $this->basePath, 'ru');

        // Act
        $roles = $builder->getAvailableRoles();

        // Assert
        self::assertSame([], $roles);
    }

    /**
     * Создаёт файл роли с указанным содержимым.
     */
    private function writeRole(string $fileName, string $content): void
    {
        file_put_contents($this->rolesDir . '/' . $fileName, $content);
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
