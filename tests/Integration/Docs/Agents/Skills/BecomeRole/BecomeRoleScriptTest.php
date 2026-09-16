<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Docs\Agents\Skills\BecomeRole;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Integration-тест скрипта become-role.sh.
 *
 * Запускает реальный скрипт на реальном проекте (включая bin/console) и
 * проверяет, что вывод содержит путь к файлу роли и XML-блок её skills.
 */
#[Group('integration')]
#[CoversNothing]
final class BecomeRoleScriptTest extends TestCase
{
    /**
     * @param array<string, string> $env
     */
    private function runScript(string $roleOrFile, array $env = []): Process
    {
        $projectRoot = dirname(__DIR__, 6);
        $script = $projectRoot . '/docs/agents/skills/become-role/scripts/become-role.sh';

        $process = new Process(['bash', $script, $roleOrFile], cwd: $projectRoot, env: $env);
        $process->run();

        return $process;
    }

    /**
     * Временный «host-проект»: docs/agents/{roles,skills} из репозитория,
     * плюс симлинки bin и vendor (CLI-бинарь вычисляется скриптом от
     * физического расположения — PACKAGE_ROOT/bin/task-orchestrator),
     * чтобы CLI-резолвинг работал из произвольного каталога.
     */
    private function createTempHost(): string
    {
        $projectRoot = dirname(__DIR__, 6);
        $temp = sys_get_temp_dir() . '/become-role-host-' . bin2hex(random_bytes(6));
        $fs = new Filesystem();
        $fs->mkdir($temp . '/docs/agents');
        $fs->mkdir($temp . '/.agents/skills');
        $fs->mirror($projectRoot . '/docs/agents/roles', $temp . '/docs/agents/roles');
        $fs->mirror($projectRoot . '/docs/agents/skills', $temp . '/docs/agents/skills');
        symlink($projectRoot . '/bin', $temp . '/bin');
        symlink($projectRoot . '/vendor', $temp . '/vendor');

        return $temp;
    }

    /**
     * Управляемая копия скилла (модель PHAR-установки): реальный каталог
     * .agents/skills/become-role без симлинка. $pharBinding === null — файл
     * привязки не создаётся (source/Composer-модель), иначе — записывается
     * переданное содержимое (пустая строка или путь к PHAR).
     */
    private function createManagedCopy(string $temp, ?string $pharBinding = null): string
    {
        $projectRoot = dirname(__DIR__, 6);
        $skillTarget = $temp . '/.agents/skills/become-role';
        (new Filesystem())->mirror(
            $projectRoot . '/docs/agents/skills/become-role',
            $skillTarget,
        );
        if ($pharBinding !== null) {
            file_put_contents($skillTarget . '/.phar-binding', $pharBinding);
        }

        return $skillTarget;
    }

    #[Test]
    public function withoutConfiguredLocaleFindsAvailableLocalizedRole(): void
    {
        // Act: пустая env эквивалентна отсутствующей настройке; роль существует
        // только как локализованный `.ru.md` и всё равно должна быть найдена.
        $process = $this->runScript('team_lead_alex', ['TASK_ORCHESTRATOR_LOCALE' => '']);

        // Assert
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $output = $process->getOutput();

        self::assertStringContainsString('Роль: team_lead_alex', $output);
        self::assertStringContainsString('Файл роли: docs/agents/roles/team/team_lead_alex.ru.md', $output);
        self::assertStringContainsString('<available_skills>', $output);
        // run-subagent — прямая декларация + транзитивная зависимость других skills Тимлида.
        self::assertStringContainsString('<name>run-subagent</name>', $output);
        // run-subagent должен идти раньше epic-via-subagents (depends_on).
        $runPos = mb_strpos($output, '<name>run-subagent</name>');
        $epicPos = mb_strpos($output, '<name>epic-via-subagents</name>');
        self::assertNotFalse($runPos);
        self::assertNotFalse($epicPos);
        self::assertLessThan($epicPos, $runPos);
    }

    #[Test]
    public function becomeRoleAcceptsRoleFilePath(): void
    {
        // Arrange — вызвать через путь к файлу роли, а не по имени.
        // Act
        $process = $this->runScript('docs/agents/roles/team/team_lead_alex.ru.md');

        // Assert — имя роли выводится из basename файла; skills каталог присутствует.
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $output = $process->getOutput();

        self::assertStringContainsString('Роль: team_lead_alex', $output);
        self::assertStringContainsString('<available_skills>', $output);
    }

    #[Test]
    public function becomeRoleAcceptsAbsoluteRoleFilePath(): void
    {
        // Act — абсолютный путь к файлу роли (cwd агента не важен).
        $projectRoot = dirname(__DIR__, 6);
        $process = $this->runScript(
            $projectRoot . '/docs/agents/roles/team/team_lead_alex.ru.md',
        );

        // Assert
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('Роль: team_lead_alex', $process->getOutput());
    }

    #[Test]
    public function becomeRoleResolvesRelativeRolePathFromSkillDirThroughSymlink(): void
    {
        // Regression: агент делает `cd .agents/skills/become-role` (симлинк на
        // docs/agents/skills/become-role) и передаёт относительный путь к файлу
        // роли с «../», отсчитанный от ЛОГИЧЕСКОГО PWD. Физический cwd при этом
        // указывает внутрь docs/agents/skills/…, и резолв от getcwd мажет:
        // скрипт обязан проверять также логический PWD (семантика cd -L).
        // Окружение воспроизводится во временном каталоге (без зависимости от
        // локальной установки `.agents/` через agent:init, которой нет в CI).
        $projectRoot = dirname(__DIR__, 6);
        $temp = $this->createTempHost();
        symlink($projectRoot . '/docs/agents/skills/become-role', $temp . '/.agents/skills/become-role');

        $skillDir = $temp . '/.agents/skills/become-role';

        try {
            $process = new Process(
                ['bash', 'scripts/become-role.sh', '../../../docs/agents/roles/team/team_lead_alex.ru.md'],
                cwd: $skillDir,
                // Логический PWD, как после `cd` по симлинку в интерактивном шелле.
                env: ['PWD' => $skillDir],
            );
            $process->run();

            // Assert
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertStringContainsString('Роль: team_lead_alex', $process->getOutput());
        } finally {
            (new Filesystem())->remove($temp);
        }
    }

    #[Test]
    public function becomeRoleReportsExplicitErrorOnMissingRoleFile(): void
    {
        // Regression: аргумент похож на путь, но файла нет — раньше путь целиком
        // уходил в CLI как «имя роли» и агент получал бессвязную диагностику
        // (usage CLI + «не удалось получить данные роли»). Теперь — явная
        // ошибка «файл роли не найден» с подсказкой передать имя роли.
        $process = $this->runScript('docs/agents/roles/team/ghost_role_xyz.ru.md');

        // Assert
        self::assertSame(1, $process->getExitCode());
        $error = $process->getErrorOutput();
        self::assertStringContainsString('файл роли не найден', $error);
        self::assertStringContainsString('имя роли', $error);
        // Старый симптом: usage-подсказка CLI вместо диагностики скрипта.
        self::assertStringNotContainsString('agent:role-skills [--format', $error);
        self::assertStringNotContainsString('не удалось получить данные роли', $error);
    }

    #[Test]
    public function becomeRoleFailsOnUnknownRole(): void
    {
        // Act
        $process = $this->runScript('definitely_unknown_role_xyz');

        // Assert
        self::assertNotSame(0, $process->getExitCode());
        self::assertNotEmpty($process->getErrorOutput());
    }

    #[Test]
    public function failsWithUsageWhenNoArgumentGiven(): void
    {
        // Матрица «аргументы»: вызов без аргумента — usage и ненулевой exit,
        // без попыток резолвинга и без обращения к CLI.
        $projectRoot = dirname(__DIR__, 6);
        $script = $projectRoot . '/docs/agents/skills/become-role/scripts/become-role.sh';

        $process = new Process(['bash', $script], cwd: $projectRoot);
        $process->run();

        // Assert
        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('Использование:', $process->getErrorOutput());
    }

    #[Test]
    public function acceptsRoleFileBasenameInCwdWithoutSlash(): void
    {
        // Матрица «аргументы»: basename файла роли в cwd без «/» — имя роли
        // извлекается из файла, а не уходит в CLI как есть.
        $temp = $this->createTempHost();
        copy(
            $temp . '/docs/agents/roles/team/team_lead_alex.ru.md',
            $temp . '/team_lead_alex.ru.md',
        );
        $script = $temp . '/docs/agents/skills/become-role/scripts/become-role.sh';

        try {
            $process = new Process(['bash', $script, 'team_lead_alex.ru.md'], cwd: $temp);
            $process->run();

            // Assert
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertStringContainsString('Роль: team_lead_alex', $process->getOutput());
        } finally {
            (new Filesystem())->remove($temp);
        }
    }

    #[Test]
    public function resolvesRoleNameFromArbitraryCwdOfTempHost(): void
    {
        // Матрица «cwd»: произвольный каталог host-проекта (не корень репо и не
        // каталог скилла) + абсолютный путь к скрипту. PROJECT_ROOT не
        // определяется (суффикс .agents/... в SCRIPT_DIR отсутствует) — CLI
        // резолвит роли от cwd и обязан найти их в temp-host.
        $temp = $this->createTempHost();
        mkdir($temp . '/some/nested/dir', 0777, true);
        $script = $temp . '/docs/agents/skills/become-role/scripts/become-role.sh';

        try {
            $process = new Process(['bash', $script, 'team_lead_alex'], cwd: $temp . '/some/nested/dir');
            $process->run();

            // Assert
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertStringContainsString('Роль: team_lead_alex', $process->getOutput());
        } finally {
            (new Filesystem())->remove($temp);
        }
    }

    #[Test]
    public function managedCopyReportsExplicitErrorOnStalePharBinding(): void
    {
        // Матрица «PHAR-привязка»: файл привязки указывает на перемещённый/
        // удалённый PHAR — явная диагностика с подсказкой agent:init --force
        // до любого обращения к CLI (без fallback на host bin/task-orchestrator).
        $temp = $this->createTempHost();
        $skillDir = $this->createManagedCopy(
            $temp,
            $temp . '/definitely-missing/task-orchestrator.phar',
        );

        try {
            $process = new Process(
                ['bash', 'scripts/become-role.sh', 'team_lead_alex'],
                cwd: $skillDir,
            );
            $process->run();

            // Assert
            self::assertSame(1, $process->getExitCode());
            $error = $process->getErrorOutput();
            self::assertStringContainsString('PHAR из runtime-привязки не найден', $error);
            self::assertStringContainsString('agent:init --force', $error);
            self::assertStringNotContainsString('Нет такого файла или каталога', $error);
        } finally {
            (new Filesystem())->remove($temp);
        }
    }

    #[Test]
    public function becomeRoleReportsExplicitErrorOnEmptyPharBinding(): void
    {
        // Regression (QA-2): пустая runtime-привязка .phar-binding — не fallback
        // на host bin/task-orchestrator, которого в managed-копии нет: явная
        // диагностика с подсказкой agent:init --force, до любого обращения к CLI.
        // Управляемая копия воспроизводится во временном каталоге (source-копия
        // skill в пакете привязки не имеет — файл создаёт только PHAR-установка).
        $temp = sys_get_temp_dir() . '/become-role-binding-' . bin2hex(random_bytes(6));
        $skillScripts = $temp . '/.agents/skills/become-role/scripts';
        mkdir($skillScripts, 0777, true);
        copy(dirname(__DIR__, 6) . '/docs/agents/skills/become-role/scripts/become-role.sh', $skillScripts . '/become-role.sh');
        // Пустая привязка: файл существует, физического пути нет.
        file_put_contents($temp . '/.agents/skills/become-role/.phar-binding', '');

        try {
            $process = new Process(
                ['bash', $skillScripts . '/become-role.sh', 'backend_developer_levsha'],
                cwd: $temp,
            );
            $process->run();

            // Assert
            self::assertSame(1, $process->getExitCode());
            $error = $process->getErrorOutput();
            self::assertStringContainsString('runtime-привязка PHAR пуста или повреждена', $error);
            self::assertStringContainsString('agent:init --force', $error);
            // Не ушло в fallback-попытку запуска host bin/task-orchestrator.
            self::assertStringNotContainsString('Нет такого файла или каталога', $error);
        } finally {
            (new Filesystem())->remove($temp);
        }
    }
}
