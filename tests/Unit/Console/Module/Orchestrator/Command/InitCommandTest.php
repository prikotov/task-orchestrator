<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Console\Module\Orchestrator\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use TaskOrchestrator\Console\Module\Orchestrator\Command\InitCommand;

#[CoversClass(InitCommand::class)]
final class InitCommandTest extends TestCase
{
    #[Test]
    public function executeFromPharFailsBeforeFilesystemWrites(): void
    {
        // Arrange
        $command = new InitCommand(
            packageDir: 'phar:///tmp/task-orchestrator.phar',
            basePath: '/host-project',
            isPhar: true,
            filesystem: $this->createFilesystemThatRejectsWrites(),
        );
        $tester = new CommandTester($command);

        // Act
        $exit = $tester->execute([]);

        // Assert
        self::assertSame(1, $exit);
        self::assertStringContainsString('недоступен в PHAR', $tester->getDisplay());
        self::assertStringContainsString('Composer', $tester->getDisplay());
        self::assertStringContainsString(
            'php vendor/bin/task-orchestrator agent:init',
            $tester->getDisplay(),
        );
        self::assertStringNotContainsString('phar://', $tester->getDisplay());
    }

    #[Test]
    public function executeFromPharWithForceFailsBeforeFilesystemWrites(): void
    {
        // Arrange
        $command = new InitCommand(
            packageDir: 'phar:///tmp/task-orchestrator.phar',
            basePath: '/host-project',
            isPhar: true,
            filesystem: $this->createFilesystemThatRejectsWrites(),
        );
        $tester = new CommandTester($command);

        // Act
        $exit = $tester->execute(['--force' => true]);

        // Assert
        self::assertSame(1, $exit);
        self::assertStringContainsString('недоступен в PHAR', $tester->getDisplay());
        self::assertStringContainsString(
            'php vendor/bin/task-orchestrator agent:init',
            $tester->getDisplay(),
        );
    }

    private function createFilesystemThatRejectsWrites(): Filesystem
    {
        $filesystem = $this->createMock(Filesystem::class);

        foreach (['mkdir', 'remove', 'symlink', 'copy'] as $method) {
            $filesystem->expects(self::never())->method($method);
        }

        return $filesystem;
    }
}
