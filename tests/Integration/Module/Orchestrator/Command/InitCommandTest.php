<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\Orchestrator\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use TaskOrchestrator\Console\Module\Orchestrator\Command\InitCommand;

use function is_link;
use function readlink;
use function sys_get_temp_dir;

/**
 * Integration-тест команды agent:init.
 *
 * Проверяет создание симлинка become-role в «host-проекте» (temp dir),
 * идемпотентность и поведение --force.
 */
#[Group('integration')]
#[CoversClass(InitCommand::class)]
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

        $this->command = new InitCommand(
            packageDir: $this->packageDir,
            basePath: $this->basePath,
            filesystem: $this->filesystem,
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
