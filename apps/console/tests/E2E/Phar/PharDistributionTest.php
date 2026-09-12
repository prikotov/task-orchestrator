<?php

declare(strict_types=1);

namespace TaskOrchestrator\Console\Tests\E2E\Phar;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * E2E-тест распространяемого PHAR-дистрибутива.
 *
 * Расширенные гарантии дистрибутива, вынесенные из короткого production-safe
 * `bin/phar-smoke` (который обязан работать без dev-зависимостей): полный
 * контракт `agent:init` (установка управляемой копии become-role, идемпотентный
 * повтор, конфликт без `--force`, замена через `--force`), запуск установленного
 * `become-role.sh` через runtime-привязку и регрессия перемещения PHAR A → B на
 * дефолтном кеше без ручной чистки (изоляция кеша по физическому пути архива).
 *
 * Тест собирает настоящий PHAR через Box один раз на класс (дорогая сборка не
 * повторяется), размещает артефакт и host-каталоги в собственном временном
 * workspace и гарантированно удаляет всё в tearDownAfterClass.
 *
 * Тест расположен в `apps/console/tests/E2E/` по конвенции E2E-тестов:
 * проверяемым публичным интерфейсом дистрибутива является консольное приложение.
 * Каталог не входит в дефолтные testsuites `phpunit.xml.dist`: тяжёлый E2E
 * запускается отдельной целью `make phar-e2e` с `PHAR_E2E_REQUIRED=1`.
 *
 * Доступность Box: обычный прямой прогон без Box корректно skip'ается;
 * локальная пред-PR цель `make phar-e2e` без Box падает красным, а не зеленеет
 * (см. self::assertBoxAvailableOrFail).
 */
#[Group('e2e')]
#[CoversNothing]
final class PharDistributionTest extends TestCase
{
    /** Явный признак обязательного запуска: без Box тест не skip'ается, а падает. */
    private const string REQUIRED_ENV = 'PHAR_E2E_REQUIRED';

    private const int BUILD_TIMEOUT = 300;

    private static string $projectRoot;
    private static string $workspace;
    private static string $pharPath;
    private static Filesystem $fs;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__, 5);
        self::$fs = new Filesystem();

        self::assertBoxAvailableOrFail();

        self::$workspace = sprintf('%s/phar-e2e-%s', sys_get_temp_dir(), bin2hex(random_bytes(8)));
        self::$fs->mkdir(self::$workspace);

        // Box пишет PHAR в корень checkout по конфигу; сразу переносим артефакт
        // в workspace, чтобы не оставлять его в рабочем каталоге между тестами.
        $builtPhar = self::$projectRoot . '/task-orchestrator.phar';
        self::$fs->remove($builtPhar);

        try {
            $build = new Process(
                ['box', 'compile', '--config=box.json.dist'],
                cwd: self::$projectRoot,
                timeout: self::BUILD_TIMEOUT,
            );
            $build->run();

            if ($build->getExitCode() !== 0 || !is_file($builtPhar)) {
                throw new RuntimeException(sprintf(
                    "Box failed to build the distribution PHAR (exit %d).\n%s\n%s",
                    (int) $build->getExitCode(),
                    $build->getOutput(),
                    $build->getErrorOutput(),
                ));
            }

            self::$pharPath = self::$workspace . '/task-orchestrator.phar';
            self::$fs->rename($builtPhar, self::$pharPath, true);
        } catch (Throwable $error) {
            self::cleanupArtifacts();

            throw $error;
        }
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        self::cleanupArtifacts();
    }

    private static function cleanupArtifacts(): void
    {
        if (!isset(self::$fs)) {
            return;
        }

        if (isset(self::$workspace) && is_dir(self::$workspace)) {
            self::$fs->remove(self::$workspace);
        }

        // Страховка: сборка Box в корне checkout не остаётся даже при сбое
        // между compile и переносом артефакта в workspace.
        if (isset(self::$projectRoot)) {
            self::$fs->remove(self::$projectRoot . '/task-orchestrator.phar');
        }
    }

    #[Test]
    public function testAgentInitFromForeignCwdInstallsManagedCopyBoundToPhar(): void
    {
        // Arrange: host-проект во временном workspace, запуск из его каталога.
        $hostDir = $this->createHostDir();
        $skillDir = $hostDir . '/.agents/skills/become-role';

        // Act
        $process = $this->runPhar(['agent:init'], $hostDir, 'install');

        // Assert: успешная установка обычного каталога (не симлинка) с полным
        // деревом skill и runtime-привязкой к физическому пути запущенного PHAR.
        self::assertSame(0, $process->getExitCode(), $this->processOutput($process));
        self::assertStringContainsString('установлен', $process->getOutput());

        self::assertDirectoryExists($skillDir);
        self::assertFileExists($skillDir);
        self::assertNotTrue(is_link($skillDir), 'PHAR-установка не должна создавать симлинк');

        foreach (['SKILL.md', 'README.md', 'scripts/become-role.sh', '.phar-binding'] as $file) {
            self::assertFileExists($skillDir . '/' . $file);
        }

        self::assertSame(self::$pharPath, (string) file_get_contents($skillDir . '/.phar-binding'));
    }

    #[Test]
    public function testRepeatedAgentInitIsIdempotentAndKeepsInstalledFilesIntact(): void
    {
        // Arrange
        $hostDir = $this->createHostDir();
        $skillMd = $hostDir . '/.agents/skills/become-role/SKILL.md';
        $first = $this->runPhar(['agent:init'], $hostDir, 'idempotent-install');
        self::assertSame(0, $first->getExitCode(), $this->processOutput($first));

        $inodeBefore = (int) fileinode($skillMd);
        $contentBefore = (string) file_get_contents($skillMd);

        // Act: повторный запуск без --force.
        $repeat = $this->runPhar(['agent:init'], $hostDir, 'idempotent-repeat');

        // Assert: успех с признанием актуальной копии; файл не переписан
        // (inode и содержимое не сменились).
        self::assertSame(0, $repeat->getExitCode(), $this->processOutput($repeat));
        self::assertStringContainsString('уже установлен', $repeat->getOutput());

        self::assertSame($inodeBefore, (int) fileinode($skillMd), 'повторный запуск переписал совпадающую копию');
        self::assertSame($contentBefore, (string) file_get_contents($skillMd));
    }

    #[Test]
    public function testConflictingInstallWithoutForceFailsWithDiagnosticsAndKeepsTarget(): void
    {
        // Arrange
        $hostDir = $this->createHostDir();
        $skillMd = $hostDir . '/.agents/skills/become-role/SKILL.md';
        $install = $this->runPhar(['agent:init'], $hostDir, 'conflict-install');
        self::assertSame(0, $install->getExitCode(), $this->processOutput($install));

        file_put_contents($skillMd, 'tampered');

        // Act: повреждённая копия без --force.
        $process = $this->runPhar(['agent:init'], $hostDir, 'conflict-run');

        // Assert: код 1, диагностика предлагает --force, не раскрывает
        // внутренний phar://-путь и не изменяет целевой объект.
        self::assertSame(1, $process->getExitCode(), $this->processOutput($process));
        $output = $process->getOutput() . $process->getErrorOutput();
        self::assertStringContainsString('--force', $output);
        self::assertStringNotContainsString('phar://', $output);
        self::assertSame('tampered', (string) file_get_contents($skillMd));
    }

    #[Test]
    public function testForceReinstallsConflictingCopyAndLeavesNoStagingArtifacts(): void
    {
        // Arrange
        $hostDir = $this->createHostDir();
        $skillDir = $hostDir . '/.agents/skills/become-role';
        $install = $this->runPhar(['agent:init'], $hostDir, 'force-install');
        self::assertSame(0, $install->getExitCode(), $this->processOutput($install));

        $originalHash = md5_file($skillDir . '/SKILL.md');
        file_put_contents($skillDir . '/SKILL.md', 'tampered');

        // Act: замена повреждённой копии через --force из того же PHAR.
        $process = $this->runPhar(['agent:init', '--force'], $hostDir, 'force-run');

        // Assert: дерево восстановлено, привязка сохранена, staging/backup
        // артефакты (.become-role.*) рядом с target отсутствуют.
        self::assertSame(0, $process->getExitCode(), $this->processOutput($process));
        self::assertSame($originalHash, md5_file($skillDir . '/SKILL.md'));
        self::assertSame(self::$pharPath, (string) file_get_contents($skillDir . '/.phar-binding'));

        $staging = glob($hostDir . '/.agents/skills/.become-role.*');
        self::assertSame([], $staging === false ? [] : $staging, 'остались staging/backup артефакты');
    }

    #[Test]
    public function testInstalledBecomeRoleScriptResolvesTestRoleThroughPharBinding(): void
    {
        // Arrange: публичный контракт установки — запуск установленного скрипта
        // через bash (executable-bit Box не гарантирует).
        $hostDir = $this->createHostDir();
        $script = $hostDir . '/.agents/skills/become-role/scripts/become-role.sh';
        $install = $this->runPhar(['agent:init'], $hostDir, 'role-install');
        self::assertSame(0, $install->getExitCode(), $this->processOutput($install));

        $process = new Process(
            ['bash', $script, 'backend_developer_levsha'],
            cwd: $hostDir,
            env: $this->isolatedCacheEnv('role-script'),
        );
        $process->setTimeout(120);
        $process->run();

        // Assert: тестовая роль разрешается через CLI привязанного PHAR.
        self::assertSame(0, $process->getExitCode(), $this->processOutput($process));
        $output = $process->getOutput();
        self::assertStringContainsString('Роль: backend_developer_levsha', $output);
        self::assertStringContainsString('Файл роли:', $output);
    }

    #[Test]
    public function testPharMoveToAnotherLocationReinstallsOnFreshDefaultCacheWithoutManualCleanup(): void
    {
        // Arrange: регрессия QA-1. Дефолтный кеш (БЕЗ APP_CACHE_DIR) изолируется
        // по физическому пути архива; TMPDIR-песочница лишь уводит системный
        // временный каталог из /tmp, кеш между запусками A/B не чистится.
        $hostDir = $this->createHostDir();
        $toolsA = $this->workspacePath('tools-a');
        $toolsB = $this->workspacePath('tools-b');
        $sandboxTmp = $this->workspacePath('tmpdir');
        self::$fs->mkdir([$toolsA, $toolsB, $sandboxTmp]);

        $pharA = $toolsA . '/task-orchestrator.phar';
        $pharB = $toolsB . '/task-orchestrator.phar';
        copy(self::$pharPath, $pharA);

        $skillDir = $hostDir . '/.agents/skills/become-role';

        // Act & Assert (сквозной сценарий перемещения).
        // 1) Первая установка из A на дефолтном кеше.
        $installFromA = $this->runPhar(['agent:init'], $hostDir, tmpDir: $sandboxTmp, pharPath: $pharA);
        self::assertSame(0, $installFromA->getExitCode(), $this->processOutput($installFromA));
        self::assertSame($pharA, (string) file_get_contents($skillDir . '/.phar-binding'));

        // 2) Перемещение архива A → B без чистки кеша.
        rename($pharA, $pharB);

        // 3) Запуск из B без --force — честный конфликт: привязка указывает на A.
        $conflictFromB = $this->runPhar(['agent:init'], $hostDir, tmpDir: $sandboxTmp, pharPath: $pharB);
        self::assertSame(1, $conflictFromB->getExitCode(), $this->processOutput($conflictFromB));
        self::assertStringContainsString('--force', $conflictFromB->getOutput() . $conflictFromB->getErrorOutput());
        self::assertSame($pharA, (string) file_get_contents($skillDir . '/.phar-binding'));

        // 4) --force из B работает на свежем кеше B (не мёртвом phar:// из A).
        $reinstallFromB = $this->runPhar(['agent:init', '--force'], $hostDir, tmpDir: $sandboxTmp, pharPath: $pharB);
        self::assertSame(0, $reinstallFromB->getExitCode(), $this->processOutput($reinstallFromB));
        self::assertSame($pharB, (string) file_get_contents($skillDir . '/.phar-binding'));

        // 5) Установленный become-role.sh работает через обновлённую привязку B.
        $roleScript = new Process(
            ['bash', $skillDir . '/scripts/become-role.sh', 'backend_developer_levsha'],
            cwd: $hostDir,
            env: [
                'TMPDIR' => $sandboxTmp,
                'APP_CACHE_DIR' => false,
                'APP_LOG_DIR' => false,
            ],
        );
        $roleScript->setTimeout(120);
        $roleScript->run();
        self::assertSame(0, $roleScript->getExitCode(), $this->processOutput($roleScript));
        self::assertStringContainsString('Роль: backend_developer_levsha', $roleScript->getOutput());
        self::assertStringContainsString('Файл роли:', $roleScript->getOutput());

        // 6) Прямое доказательство изоляции: для A и B материализованы разные
        // корни дефолтного кеша — контейнер A не переиспользуется запуском из B.
        $cacheRoots = glob($sandboxTmp . '/task-orchestrator/*', GLOB_ONLYDIR);
        self::assertNotFalse($cacheRoots);
        self::assertGreaterThanOrEqual(2, count($cacheRoots), 'дефолтный кеш PHAR не изолирован по физическому пути архива');
    }

    /**
     * Обязательность доступности Box: явный прогон цели `make phar-e2e`
     * (PHAR_E2E_REQUIRED=1) без Box падает красным — тест не может ложно
     * позеленеть; обычный прямой локальный запуск — skip.
     */
    private static function assertBoxAvailableOrFail(): void
    {
        $box = (new ExecutableFinder())->find('box');

        if ($box !== null) {
            return;
        }

        $required = getenv(self::REQUIRED_ENV) === '1';

        if ($required) {
            throw new RuntimeException(
                'Box is required for the PHAR distribution E2E test but was not found in PATH. '
                . 'Install Box locally or use setup-php with: tools: box',
            );
        }

        self::markTestSkipped('Box is not installed; PHAR distribution E2E requires a real Box build.');
    }

    private static function workspacePath(string $name): string
    {
        return self::$workspace . '/' . $name . '-' . uniqid();
    }

    /**
     * @param list<string> $arguments
     */
    private function runPhar(
        array $arguments,
        string $cwd,
        ?string $cacheName = null,
        ?string $tmpDir = null,
        ?string $pharPath = null,
    ): Process {
        /** @var array<string, string|false> $env */
        $env = [
            'APP_CACHE_DIR' => false,
            'APP_LOG_DIR' => false,
        ];
        if ($cacheName !== null) {
            $env = $this->isolatedCacheEnv($cacheName) + $env;
        }
        if ($tmpDir !== null) {
            $env['TMPDIR'] = $tmpDir;
        }

        $process = new Process(['php', $pharPath ?? self::$pharPath, ...$arguments], cwd: $cwd, env: $env);
        $process->setTimeout(180);
        $process->run();

        return $process;
    }

    private function createHostDir(): string
    {
        $hostDir = $this->workspacePath('host');
        self::$fs->mkdir($hostDir);

        return $hostDir;
    }

    /**
     * @return array<string, string>
     */
    private function isolatedCacheEnv(string $name): array
    {
        $base = self::$workspace . '/state-' . $name;

        return [
            'APP_CACHE_DIR' => $base . '/cache',
            'APP_LOG_DIR' => $base . '/log',
        ];
    }

    private function processOutput(Process $process): string
    {
        return $process->getOutput() . "\n--- stderr ---\n" . $process->getErrorOutput();
    }
}
