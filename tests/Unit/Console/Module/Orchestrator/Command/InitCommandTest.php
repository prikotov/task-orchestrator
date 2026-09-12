<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Console\Module\Orchestrator\Command;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use TaskOrchestrator\Console\Module\Orchestrator\Command\InitCommand;
use TaskOrchestrator\Console\Module\Orchestrator\Skill\BecomeRoleInstallOutcomeEnum;
use TaskOrchestrator\Console\Module\Orchestrator\Skill\BecomeRoleInstallResultDto;
use TaskOrchestrator\Console\Module\Orchestrator\Skill\InstallBecomeRoleServiceInterface;

/**
 * Unit-тест тонкой команды agent:init.
 *
 * Команда — транспортная граница: разбор --force, блокировка конкурентного
 * запуска, делегирование специализированному сервису установки, отображение
 * результата и код завершения. Файловая механика покрыта integration-тестами
 * {@see \TaskOrchestrator\Tests\Integration\Module\Orchestrator\Skill\InstallBecomeRoleServiceTest}.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(InitCommand::class)]
final class InitCommandTest extends TestCase
{
    #[Test]
    public function executeDelegatesToInstallerWithoutForce(): void
    {
        // Arrange
        $installer = $this->createMock(InstallBecomeRoleServiceInterface::class);
        $installer
            ->expects(self::once())
            ->method('install')
            ->with(false)
            ->willReturn(new BecomeRoleInstallResultDto(
                BecomeRoleInstallOutcomeEnum::installed,
                'become-role установлен: /host/.agents/skills/become-role',
            ));
        $tester = new CommandTester(new InitCommand($installer, $this->createLockFactory()));

        // Act
        $exit = $tester->execute([]);

        // Assert
        self::assertSame(0, $exit);
        self::assertStringContainsString(
            'become-role установлен: /host/.agents/skills/become-role',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function executePassesForceOptionToInstaller(): void
    {
        // Arrange
        $installer = $this->createMock(InstallBecomeRoleServiceInterface::class);
        $installer
            ->expects(self::once())
            ->method('install')
            ->with(true)
            ->willReturn(new BecomeRoleInstallResultDto(
                BecomeRoleInstallOutcomeEnum::installed,
                'become-role установлен',
            ));
        $tester = new CommandTester(new InitCommand($installer, $this->createLockFactory()));

        // Act
        $exit = $tester->execute(['--force' => true]);

        // Assert
        self::assertSame(0, $exit);
    }

    #[Test]
    public function executeTreatsAlreadyInstalledAsSuccess(): void
    {
        // Arrange
        $installer = $this->createMock(InstallBecomeRoleServiceInterface::class);
        $installer->method('install')->willReturn(new BecomeRoleInstallResultDto(
            BecomeRoleInstallOutcomeEnum::alreadyInstalled,
            'become-role уже установлен: /host/.agents/skills/become-role',
        ));
        $tester = new CommandTester(new InitCommand($installer, $this->createLockFactory()));

        // Act
        $exit = $tester->execute([]);

        // Assert
        self::assertSame(0, $exit);
        self::assertStringContainsString('уже установлен', $tester->getDisplay());
    }

    #[Test]
    public function executeRendersConflictAsWarningAndFails(): void
    {
        // Arrange — SymfonyStyle переносит длинные строки, поэтому проверяются
        // короткие стабильные фрагменты сообщения.
        $installer = $this->createMock(InstallBecomeRoleServiceInterface::class);
        $installer->method('install')->willReturn(
            new BecomeRoleInstallResultDto(
                BecomeRoleInstallOutcomeEnum::conflict,
                'Путь /host/.agents/skills/become-role существует и отличается. Используйте --force для замены.',
            ),
        );
        $tester = new CommandTester(new InitCommand($installer, $this->createLockFactory()));

        // Act
        $exit = $tester->execute([]);

        // Assert
        self::assertSame(1, $exit);
        self::assertStringContainsString('существует и отличается', $tester->getDisplay());
        self::assertStringContainsString('--force', $tester->getDisplay());
    }

    #[Test]
    public function executeRendersFatalOutcomeAsErrorAndFails(): void
    {
        // Arrange
        $message = 'Встроенный skill become-role не найден или неполон в PHAR.';
        $installer = $this->createMock(InstallBecomeRoleServiceInterface::class);
        $installer->method('install')->willReturn(
            new BecomeRoleInstallResultDto(BecomeRoleInstallOutcomeEnum::sourceMissing, $message),
        );
        $tester = new CommandTester(new InitCommand($installer, $this->createLockFactory()));

        // Act
        $exit = $tester->execute([]);

        // Assert
        self::assertSame(1, $exit);
        self::assertStringContainsString($message, $tester->getDisplay());
    }

    #[Test]
    public function executeSkipsInstallWhileConcurrentRunHoldsLock(): void
    {
        // Arrange
        $installer = $this->createMock(InstallBecomeRoleServiceInterface::class);
        $installer->expects(self::never())->method('install');
        $tester = new CommandTester(new InitCommand($installer, $this->createBusyLockFactory()));

        // Act
        $exit = $tester->execute([]);

        // Assert
        self::assertSame(0, $exit);
        self::assertStringContainsString('уже выполняется', $tester->getDisplay());
    }

    #[Test]
    public function executeReleasesLockAfterInstall(): void
    {
        // Arrange
        $installer = $this->createMock(InstallBecomeRoleServiceInterface::class);
        $installer->method('install')->willReturn(new BecomeRoleInstallResultDto(
            BecomeRoleInstallOutcomeEnum::installed,
            'become-role установлен',
        ));
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects(self::once())->method('release');
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);
        $tester = new CommandTester(new InitCommand($installer, $lockFactory));

        // Act
        $exit = $tester->execute([]);

        // Assert
        self::assertSame(0, $exit);
    }

    private function createLockFactory(): LockFactory
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        return $lockFactory;
    }

    private function createBusyLockFactory(): LockFactory
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        return $lockFactory;
    }
}
