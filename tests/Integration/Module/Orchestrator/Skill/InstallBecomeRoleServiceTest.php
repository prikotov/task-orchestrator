<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\Orchestrator\Skill;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TaskOrchestrator\Console\Module\Orchestrator\Skill\BecomeRoleInstallOutcomeEnum;
use TaskOrchestrator\Console\Module\Orchestrator\Skill\Service\InstallBecomeRoleService;

/**
 * Integration-тест PHAR-ветки InstallBecomeRoleService на реальной временной ФС.
 *
 * Источник воспроизводится обычным каталогом (материализация и сравнение деревьев
 * не зависят от схемы phar://); поведение на реально собранном PHAR дополнительно
 * проверяет bin/phar-smoke. Source/Composer-ветка (симлинк) покрыта регрессионным
 * {@see \TaskOrchestrator\Tests\Integration\Module\Orchestrator\Command\InitCommandTest}.
 */
#[Group('integration')]
#[CoversClass(InstallBecomeRoleService::class)]
final class InstallBecomeRoleServiceTest extends TestCase
{
    private const string PHAR_PATH = '/opt/fake/task-orchestrator.phar';

    private const string MOVED_PHAR_PATH = '/opt/moved/task-orchestrator.phar';

    private string $temp;

    private string $basePath;

    private string $sourceDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->temp = sys_get_temp_dir() . '/become-role-install-' . bin2hex(random_bytes(6));
        $this->basePath = $this->temp . '/host';
        $this->sourceDir = $this->temp . '/package/docs/agents/skills/become-role';
        $this->filesystem = new Filesystem();

        mkdir($this->basePath, 0777, true);
        mkdir($this->sourceDir . '/scripts', 0777, true);
        file_put_contents($this->sourceDir . '/SKILL.md', "# skill\n");
        file_put_contents($this->sourceDir . '/README.md', "# readme\n");
        file_put_contents($this->sourceDir . '/scripts/become-role.sh', "#!/usr/bin/env bash\n");
    }

    protected function tearDown(): void
    {
        // Возврат прав перед удалением (тесты могут оставить каталог read-only).
        if (is_dir($this->basePath . '/.agents/skills')) {
            @chmod($this->basePath . '/.agents/skills', 0777);
        }

        if (is_dir($this->sourceDir . '/scripts')) {
            @chmod($this->sourceDir . '/scripts', 0777);
        }

        $this->filesystem->remove($this->temp);
    }

    #[Test]
    public function installCreatesManagedCopyWithRuntimeBindingWhenTargetMissing(): void
    {
        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::installed, $result->outcome);
        self::assertStringContainsString('управляемая копия из PHAR', $result->message);

        $target = $this->targetPath();
        self::assertTrue(is_dir($target));
        self::assertFalse(is_link($target), 'управляемая копия не должна быть симлинком');
        self::assertFileExists($target . '/SKILL.md');
        self::assertFileExists($target . '/README.md');
        self::assertFileExists($target . '/scripts/become-role.sh');

        $binding = $target . '/' . InstallBecomeRoleService::PHAR_BINDING_FILENAME;
        self::assertFileExists($binding);
        self::assertSame(self::PHAR_PATH, file_get_contents($binding));

        $this->assertNoTempArtifacts();
    }

    #[Test]
    public function installIsIdempotentWhenTreeMatchesExpected(): void
    {
        // Arrange
        $this->createService()->install(false);
        $skill = $this->targetPath() . '/SKILL.md';
        touch($skill, 1234567890);
        $inodeBefore = fileinode($skill);

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::alreadyInstalled, $result->outcome);
        self::assertStringContainsString('уже установлен', $result->message);
        self::assertSame($inodeBefore, fileinode($skill), 'совпадающая копия не должна переписываться');
        self::assertSame(1234567890, filemtime($skill));

        $this->assertNoTempArtifacts();
    }

    #[Test]
    public function installFailsWithoutForceWhenExtraFileExists(): void
    {
        // Arrange
        $this->createService()->install(false);
        $this->filesystem->dumpFile($this->targetPath() . '/extra.txt', 'user file');

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::conflict, $result->outcome);
        self::assertStringContainsString('--force', $result->message);
        self::assertFileExists($this->targetPath() . '/extra.txt', 'target не должен изменяться без --force');

        $this->assertNoTempArtifacts();
    }

    #[Test]
    public function installFailsWithoutForceWhenFileContentDiffers(): void
    {
        // Arrange
        $this->createService()->install(false);
        $this->filesystem->dumpFile($this->targetPath() . '/SKILL.md', 'tampered');

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::conflict, $result->outcome);
        self::assertSame('tampered', file_get_contents($this->targetPath() . '/SKILL.md'));
    }

    #[Test]
    public function installFailsWithoutForceWhenFileMissing(): void
    {
        // Arrange
        $this->createService()->install(false);
        unlink($this->targetPath() . '/README.md');

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::conflict, $result->outcome);
        self::assertFileDoesNotExist($this->targetPath() . '/README.md');
    }

    #[Test]
    public function installForceReplacesDifferingTreeWithFullCopy(): void
    {
        // Arrange
        $this->createService()->install(false);
        $this->filesystem->dumpFile($this->targetPath() . '/SKILL.md', 'tampered');
        $this->filesystem->dumpFile($this->targetPath() . '/extra.txt', 'user file');

        // Act
        $result = $this->createService()->install(true);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::installed, $result->outcome);
        self::assertSame("# skill\n", file_get_contents($this->targetPath() . '/SKILL.md'));
        self::assertFileDoesNotExist($this->targetPath() . '/extra.txt');
        self::assertSame(
            self::PHAR_PATH,
            file_get_contents($this->targetPath() . '/' . InstallBecomeRoleService::PHAR_BINDING_FILENAME),
        );

        $this->assertNoTempArtifacts();
    }

    #[Test]
    public function installFailsWithoutForceWhenTargetIsSymlink(): void
    {
        // Arrange
        $outside = $this->temp . '/outside';
        $this->filesystem->mkdir($outside);
        $this->filesystem->dumpFile($outside . '/keep.txt', 'keep');
        $this->filesystem->mkdir($this->basePath . '/.agents/skills');
        $this->filesystem->symlink($outside, $this->targetPath());

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::conflict, $result->outcome);
        self::assertTrue(is_link($this->targetPath()), 'target-симлинк должен остаться без изменений');
        self::assertSame('keep', file_get_contents($outside . '/keep.txt'));

        $this->assertNoTempArtifacts();
    }

    #[Test]
    public function installForceMovesTargetSymlinkItselfWithoutTouchingDestination(): void
    {
        // Arrange
        $outside = $this->temp . '/outside';
        $this->filesystem->mkdir($outside);
        $this->filesystem->dumpFile($outside . '/keep.txt', 'keep');
        $this->filesystem->mkdir($this->basePath . '/.agents/skills');
        $this->filesystem->symlink($outside, $this->targetPath());

        // Act
        $result = $this->createService()->install(true);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::installed, $result->outcome);
        self::assertTrue(is_dir($this->targetPath()));
        self::assertFalse(is_link($this->targetPath()));
        self::assertSame('keep', file_get_contents($outside . '/keep.txt'), 'назначение симлинка не должно читаться/меняться');
        self::assertFileExists($this->targetPath() . '/SKILL.md');

        $this->assertNoTempArtifacts();
    }

    #[Test]
    public function installFailsWithoutForceWhenTargetIsRegularFile(): void
    {
        // Arrange
        $this->filesystem->mkdir($this->basePath . '/.agents/skills');
        $this->filesystem->dumpFile($this->targetPath(), 'file instead of skill');

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::conflict, $result->outcome);
        self::assertSame('file instead of skill', file_get_contents($this->targetPath()));
    }

    #[Test]
    public function installForceReplacesRegularFileTargetWithDirectory(): void
    {
        // Arrange
        $this->filesystem->mkdir($this->basePath . '/.agents/skills');
        $this->filesystem->dumpFile($this->targetPath(), 'file instead of skill');

        // Act
        $result = $this->createService()->install(true);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::installed, $result->outcome);
        self::assertTrue(is_dir($this->targetPath()));
        self::assertFileExists($this->targetPath() . '/SKILL.md');
    }

    #[Test]
    public function installFailsWhenInternalSymlinkExistsInTarget(): void
    {
        // Arrange
        $this->createService()->install(false);
        unlink($this->targetPath() . '/SKILL.md');
        $this->filesystem->symlink('../README.md', $this->targetPath() . '/SKILL.md');

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::conflict, $result->outcome);
        self::assertTrue(is_link($this->targetPath() . '/SKILL.md'));

        $this->assertNoTempArtifacts();
    }

    #[Test]
    public function installFailsWhenAgentsDirIsSymlink(): void
    {
        // Arrange
        $outside = $this->temp . '/outside-agents';
        $this->filesystem->mkdir($outside);
        $this->filesystem->symlink($outside, $this->basePath . '/.agents');

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::error, $result->outcome);
        self::assertStringContainsString('.agents-структура', $result->message);
        self::assertTrue(is_link($this->basePath . '/.agents'));
        self::assertSame([], $this->directoryEntries($outside), 'по симлинку .agents ничего не должно создаваться');
    }

    #[Test]
    public function installFailsWhenSkillsDirIsSymlink(): void
    {
        // Arrange
        $outside = $this->temp . '/outside-skills';
        $this->filesystem->mkdir($outside);
        $this->filesystem->mkdir($this->basePath . '/.agents');
        $this->filesystem->symlink($outside, $this->basePath . '/.agents/skills');

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::error, $result->outcome);
        self::assertTrue(is_link($this->basePath . '/.agents/skills'));
        self::assertFileDoesNotExist($this->targetPath());
        self::assertSame([], $this->directoryEntries($outside));
    }

    #[Test]
    public function installFailsWhenAgentsDirIsRegularFile(): void
    {
        // Arrange
        $this->filesystem->dumpFile($this->basePath . '/.agents', 'not a directory');

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::error, $result->outcome);
        self::assertStringContainsString('не является каталогом', $result->message);
    }

    #[Test]
    public function installFailsOnIncompleteSourceWithoutPartialTarget(): void
    {
        // Arrange — обязательный scripts/become-role.sh отсутствует в источнике
        unlink($this->sourceDir . '/scripts/become-role.sh');

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::sourceMissing, $result->outcome);
        self::assertStringContainsString('scripts/become-role.sh', $result->message);
        self::assertStringNotContainsString('phar://', $result->message);
        self::assertFileDoesNotExist($this->targetPath(), 'частично установленный target недопустим');

        $this->assertNoTempArtifacts();
    }

    #[Test]
    public function installFailsWhenSourceContainsSymlink(): void
    {
        // Arrange
        $this->filesystem->symlink('../../README.md', $this->sourceDir . '/scripts/link');

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::sourceInvalid, $result->outcome);
        self::assertFileDoesNotExist($this->targetPath());

        $this->assertNoTempArtifacts();
    }

    #[Test]
    public function installReportsStableDiagnosticWithoutSourcePathWhenSourceDirectoryUnreadable(): void
    {
        // Arrange — окружение должно уважать права чтения каталога
        $probe = $this->temp . '/probe-no-read';
        $this->filesystem->mkdir($probe);
        chmod($probe, 0000);
        $probeEntries = @scandir($probe);
        chmod($probe, 0777);
        if ($probeEntries !== false) {
            self::markTestSkipped('Окружение игнорирует права чтения каталога (root); сценарий непроверяем.');
        }

        // Снимок источника берётся до подготовки staging: права с подкаталога
        // снимаются в момент mkdir(staging) — отказ scandir воспроизводится
        // именно в copyTree, а не в snapshotTree.
        $sourceScripts = $this->sourceDir . '/scripts';
        $filesystem = new class ($sourceScripts) extends Filesystem {
            public function __construct(private readonly string $sourceScripts)
            {
            }

            #[Override]
            public function mkdir(string|iterable $dirs, int $mode = 0o777): void
            {
                parent::mkdir($dirs, $mode);
                @chmod($this->sourceScripts, 0000);
            }
        };

        $service = new InstallBecomeRoleService(
            packageDir: $this->temp . '/package',
            basePath: $this->basePath,
            isPhar: true,
            pharPath: self::PHAR_PATH,
            filesystem: $filesystem,
        );

        // Act
        $result = $service->install(false);

        // Assert — диагностика устойчива: относительный путь ресурса без
        // абсолютного пути источника и без схемы phar://
        self::assertSame(BecomeRoleInstallOutcomeEnum::error, $result->outcome);
        self::assertStringContainsString(
            'не удалось прочитать каталог встроенного skill become-role в PHAR',
            $result->message,
        );
        self::assertStringContainsString('docs/agents/skills/become-role/scripts', $result->message);
        self::assertStringNotContainsString('phar://', $result->message);
        self::assertStringNotContainsString($this->sourceDir, $result->message);
        self::assertFileDoesNotExist($this->targetPath(), 'частично установленный target недопустим');

        $this->assertNoTempArtifacts();
    }

    #[Test]
    public function installLeavesNoPartialTargetOnPreparationError(): void
    {
        // Arrange — .agents/skills недоступен для записи: staging создать нельзя
        $this->filesystem->mkdir($this->basePath . '/.agents/skills');
        chmod($this->basePath . '/.agents/skills', 0555);
        if (is_writable($this->basePath . '/.agents/skills')) {
            self::markTestSkipped('Окружение игнорирует read-only каталог (root); сценарий непроверяем.');
        }

        // Act
        $result = $this->createService()->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::error, $result->outcome);
        self::assertStringContainsString('Следующий шаг', $result->message);
        self::assertFileDoesNotExist($this->targetPath());
    }

    #[Test]
    public function installRequiresForceWhenPharMovedAndForceUpdatesBinding(): void
    {
        // Arrange
        $this->createService()->install(false);

        // Act — тот же host, но PHAR физически переехал (другая привязка)
        $conflict = $this->createService(self::MOVED_PHAR_PATH)->install(false);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::conflict, $conflict->outcome);
        self::assertSame(
            self::PHAR_PATH,
            file_get_contents($this->targetPath() . '/' . InstallBecomeRoleService::PHAR_BINDING_FILENAME),
        );

        // Act — --force обновляет runtime-привязку
        $updated = $this->createService(self::MOVED_PHAR_PATH)->install(true);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::installed, $updated->outcome);
        self::assertSame(
            self::MOVED_PHAR_PATH,
            file_get_contents($this->targetPath() . '/' . InstallBecomeRoleService::PHAR_BINDING_FILENAME),
        );
    }

    #[Test]
    public function installRollsBackPreviousTargetWhenSwitchFails(): void
    {
        // Arrange — реальная установка, затем повреждение и подмена rename
        $this->createService()->install(false);
        $this->filesystem->dumpFile($this->targetPath() . '/SKILL.md', 'tampered');

        $service = $this->createServiceWithFailingSwitch();

        // Act
        $result = $service->install(true);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::error, $result->outcome);
        self::assertStringContainsString('восстановлена', $result->message);
        self::assertSame('tampered', file_get_contents($this->targetPath() . '/SKILL.md'), 'прежняя копия восстановлена');
        self::assertFileExists($this->targetPath() . '/README.md');

        $this->assertNoTempArtifacts();
    }

    #[Test]
    public function installKeepsBackupWhenRollbackFails(): void
    {
        // Arrange
        $this->createService()->install(false);
        $this->filesystem->dumpFile($this->targetPath() . '/SKILL.md', 'tampered');

        $service = $this->createServiceWithFailingSwitchAndRollback();

        // Act
        $result = $service->install(true);

        // Assert
        self::assertSame(BecomeRoleInstallOutcomeEnum::error, $result->outcome);
        self::assertStringContainsString('резервной копии', $result->message);

        $backups = $this->tempArtifacts('.become-role.backup.*');
        self::assertCount(1, $backups, 'резервная копия должна сохраниться при невозможности отката');
        self::assertSame('tampered', file_get_contents($backups[0] . '/SKILL.md'));
        self::assertFileDoesNotExist($this->targetPath());
        self::assertSame([], $this->tempArtifacts('.become-role.staging.*'), 'staging должен быть очищен');
    }

    /**
     * Сервис с инъекцией отказа на втором rename (staging → target): первый
     * rename (target → backup) и последующий откат выполняются нативно.
     */
    private function createServiceWithFailingSwitch(): InstallBecomeRoleService
    {
        return new class (
            $this->temp . '/package',
            $this->basePath,
            true,
            self::PHAR_PATH,
            $this->filesystem,
        ) extends InstallBecomeRoleService {
            private int $renameCalls = 0;

            #[Override]
            protected function renamePath(string $from, string $to): bool
            {
                $this->renameCalls++;

                if ($this->renameCalls === 2) {
                    return false;
                }

                return parent::renamePath($from, $to);
            }
        };
    }

    /**
     * Сервис с отказом и переключения (2-й rename), и отката (3-й rename).
     */
    private function createServiceWithFailingSwitchAndRollback(): InstallBecomeRoleService
    {
        return new class (
            $this->temp . '/package',
            $this->basePath,
            true,
            self::PHAR_PATH,
            $this->filesystem,
        ) extends InstallBecomeRoleService {
            private int $renameCalls = 0;

            #[Override]
            protected function renamePath(string $from, string $to): bool
            {
                $this->renameCalls++;

                if ($this->renameCalls === 2 || $this->renameCalls === 3) {
                    return false;
                }

                return parent::renamePath($from, $to);
            }
        };
    }

    private function createService(?string $pharPath = self::PHAR_PATH): InstallBecomeRoleService
    {
        return new InstallBecomeRoleService(
            packageDir: $this->temp . '/package',
            basePath: $this->basePath,
            isPhar: true,
            pharPath: $pharPath,
            filesystem: $this->filesystem,
        );
    }

    private function targetPath(): string
    {
        return $this->basePath . '/.agents/skills/become-role';
    }

    /**
     * @return list<string>
     */
    private function directoryEntries(string $dir): array
    {
        $entries = scandir($dir);

        return $entries === false ? [] : array_values(array_diff($entries, ['.', '..']));
    }

    /**
     * @return list<string>
     */
    private function tempArtifacts(string $pattern): array
    {
        $found = glob($this->basePath . '/.agents/skills/' . $pattern);

        return $found === false ? [] : array_values($found);
    }

    private function assertNoTempArtifacts(): void
    {
        self::assertSame([], $this->tempArtifacts('.become-role.*'), 'временные артефакты staging/backup должны быть удалены');
    }
}
