<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\Orchestrator\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use TaskOrchestrator\Console\Module\Orchestrator\Command\InitCommand;
use TaskOrchestrator\Console\Module\Orchestrator\Skill\Service\InstallBecomeRoleService;

use function is_link;
use function readlink;
use function sys_get_temp_dir;

/**
 * Integration-тест команды agent:init: source/Composer-контракт.
 *
 * Регрессия публичного поведения source/Composer-ветки: относительный симлинк
 * become-role в host-проекте (temp dir), идемпотентность и --force. PHAR-ветка
 * (управляемая копия) покрыта в
 * {@see \TaskOrchestrator\Tests\Integration\Module\Orchestrator\Skill\InstallBecomeRoleServiceTest}.
 *
 * CoversClass сервиса добавлен рядом с командой: symlink-ветка установки
 * выполняется именно в InstallBecomeRoleService, и без него отчёт покрытия
 * по фильтру не видит эти строки (QA- замечание об измеримости).
 */
#[Group('integration')]
#[CoversClass(InitCommand::class)]
#[CoversClass(InstallBecomeRoleService::class)]
final class InitCommandTest extends TestCase
{
    private string $packageDir;

    private string $basePath;

    private Filesystem $filesystem;

    private InitCommand $command;

    protected function setUp(): void
    {
        $this->packageDir = __DIR__ . '/Fixtures/mini-package';
        $this->basePath = sys_get_temp_dir() . '/task-orchestrator-init-' . bin2hex(random_bytes(6));
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->basePath);

        $installer = new InstallBecomeRoleService(
            packageDir: $this->packageDir,
            basePath: $this->basePath,
            isPhar: false,
            pharPath: null,
            filesystem: $this->filesystem,
        );

        $this->command = new InitCommand(
            installer: $installer,
            lockFactory: new LockFactory(new FlockStore()),
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->basePath);
    }

    #[Test]
    public function executeCreatesSymlinkToPackageSkillInHostProject(): void
    {
        // Arrange
        $tester = new CommandTester($this->command);

        // Act
        $exit = $tester->execute([]);

        // Assert
        self::assertSame(0, $exit);
        $link = $this->basePath . '/.agents/skills/become-role';
        self::assertTrue(is_link($link), 'become-role symlink must be created');
        self::assertFileExists($link . '/SKILL.md', 'SKILL.md must be reachable through symlink');
    }

    #[Test]
    public function executeIsIdempotentWhenSymlinkAlreadyCorrect(): void
    {
        // Arrange
        $first = new CommandTester($this->command);
        $first->execute([]);
        $link = $this->basePath . '/.agents/skills/become-role';
        $firstLinkTarget = readlink($link);

        // Act — повторный запуск
        $second = new CommandTester($this->command);
        $exit = $second->execute([]);

        // Assert
        self::assertSame(0, $exit);
        self::assertTrue(is_link($link));
        self::assertSame($firstLinkTarget, readlink($link));
        self::assertStringContainsString('уже установлен', $second->getDisplay());
    }

    #[Test]
    public function executeForceReplacesIncorrectSymlink(): void
    {
        // Arrange — некорректный существующий симлинк (на /dev/null или несуществующий путь)
        $link = $this->basePath . '/.agents/skills/become-role';
        $this->filesystem->mkdir($this->basePath . '/.agents/skills');
        $this->filesystem->symlink('/nonexistent/destination', $link);

        // Act без --force → отказ
        $withoutForce = new CommandTester($this->command);
        self::assertSame(1, $withoutForce->execute([]));
        self::assertStringContainsString('--force', $withoutForce->getDisplay());

        // Act с --force → пересоздание
        $withForce = new CommandTester($this->command);
        $exit = $withForce->execute(['--force' => true]);

        // Assert
        self::assertSame(0, $exit);
        self::assertTrue(is_link($link));
        self::assertFileExists($link . '/SKILL.md');
    }
}
