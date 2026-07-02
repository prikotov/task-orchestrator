<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Docs\Agents\Skills\BecomeRole;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Integration-тест скрипта become-role.sh.
 *
 * Запускает реальный скрипт на реальном проекте (включая bin/console) и
 * проверяет, что вывод содержит путь к файлу роли и XML-каталог её skills.
 */
#[Group('integration')]
#[CoversNothing]
final class BecomeRoleScriptTest extends TestCase
{
    private function runScript(string $role): Process
    {
        $projectRoot = dirname(__DIR__, 6);
        $script = $projectRoot . '/docs/agents/skills/become-role/scripts/become-role.sh';

        $process = new Process(['bash', $script, $role], cwd: $projectRoot);
        $process->run();

        return $process;
    }

    #[Test]
    public function adoptRoleOutputsRoleFileAndSkillsCatalogForKnownRole(): void
    {
        // Act
        $process = $this->runScript('team_lead_alex');

        // Assert
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $output = $process->getOutput();

        self::assertStringContainsString('# Роль: team_lead_alex', $output);
        self::assertStringContainsString('team_lead_alex.ru.md', $output);
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
    public function adoptRoleFailsOnUnknownRole(): void
    {
        // Act
        $process = $this->runScript('definitely_unknown_role_xyz');

        // Assert
        self::assertNotSame(0, $process->getExitCode());
        self::assertNotEmpty($process->getErrorOutput());
    }
}
