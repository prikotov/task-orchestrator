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
        $projectRoot = dirname(__DIR__, 6);
        $skillDir = $projectRoot . '/.agents/skills/become-role';

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
