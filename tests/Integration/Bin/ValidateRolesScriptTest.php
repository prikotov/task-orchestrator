<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Bin;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class ValidateRolesScriptTest extends TestCase
{
    private string $tempDir;
    private string $projectRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
        $this->tempDir = sys_get_temp_dir() . '/validate-roles-test-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0777, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function validatesCurrentRoleFiles(): void
    {
        $process = $this->runValidateRoles(['--path', 'docs/agents/roles']);

        self::assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
        self::assertStringContainsString('All checks passed', $process->getOutput());
    }

    #[Test]
    public function acceptsPathOption(): void
    {
        $this->writeRole('backend_developer_levsha.ru.md');

        $process = $this->runValidateRoles(['--path', $this->tempDir]);

        self::assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
        self::assertStringContainsString('Validating 1 role file(s)', $process->getOutput());
    }

    #[Test]
    public function rejectsUnknownPersonalitySection(): void
    {
        $this->writeRole(
            'backend_developer_levsha.ru.md',
            <<<'YAML'
personality:
  unknown_model: "value"
YAML,
        );

        $process = $this->runValidateRoles(['--path', $this->tempDir]);

        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString("\033[31m", $process->getOutput());
        self::assertStringContainsString('unknown personality section "unknown_model"', $process->getOutput());
    }

    #[Test]
    public function rejectsRoleThatIncludesPersonaName(): void
    {
        $this->writeRole(
            'backend_developer_levsha.ru.md',
            <<<'YAML'
role: backend_developer_levsha
YAML,
        );

        $process = $this->runValidateRoles(['--path', $this->tempDir]);

        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('must be a role type without the persona name', $process->getOutput());
    }

    #[Test]
    public function rejectsNonLatinAgentInFilenameAndFrontMatter(): void
    {
        $this->writeRole(
            'backend_developer_левша.ru.md',
            <<<'YAML'
agent: backend_developer_левша
YAML,
        );

        $process = $this->runValidateRoles(['--path', $this->tempDir]);

        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString(
            'filename agent part "backend_developer_левша" is not snake_case Latin',
            $process->getOutput(),
        );
        self::assertStringContainsString(
            'agent "backend_developer_левша" is not snake_case Latin',
            $process->getOutput(),
        );
    }

    #[Test]
    public function rejectsScalarSkills(): void
    {
        $this->writeRole(
            'backend_developer_levsha.ru.md',
            <<<'YAML'
skills: run-subagent
YAML,
        );

        $process = $this->runValidateRoles(['--path', $this->tempDir]);

        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('skills is not an array', $process->getOutput());
    }

    /**
     * @param list<string> $arguments
     */
    private function runValidateRoles(array $arguments): Process
    {
        $process = new Process(
            array_merge([PHP_BINARY, 'bin/validate-roles'], $arguments),
            $this->projectRoot,
        );
        $process->setTimeout(20);
        $process->run();

        return $process;
    }

    private function writeRole(string $filename, string $frontMatterOverride = ''): void
    {
        $fields = [
            'agent' => 'backend_developer_levsha',
            'role' => 'backend_developer',
            'name' => '"Левша"',
            'title' => '"Бэкендер Левша"',
            'description' => '"Реализует серверную логику."',
        ];
        $extraFrontMatter = <<<'YAML'
personality:
  disc: "D5 I2 S4 C9"
skills:
  - run-subagent
YAML;

        if ($frontMatterOverride !== '') {
            foreach (explode("\n", trim($frontMatterOverride)) as $line) {
                if (preg_match('/^([a-z_]+):/', $line, $matches) === 1) {
                    unset($fields[$matches[1]]);
                }
            }

            if (str_contains($frontMatterOverride, 'personality:')) {
                $extraFrontMatter = str_replace(
                    "personality:\n  disc: \"D5 I2 S4 C9\"\n",
                    '',
                    $extraFrontMatter,
                );
            }

            if (str_contains($frontMatterOverride, 'skills:')) {
                $extraFrontMatter = str_replace(
                    "skills:\n  - run-subagent",
                    '',
                    $extraFrontMatter,
                );
            }
        }

        $frontMatter = '';
        foreach ($fields as $key => $value) {
            $frontMatter .= "{$key}: {$value}\n";
        }

        if ($extraFrontMatter !== '') {
            $frontMatter .= rtrim($extraFrontMatter) . "\n";
        }

        if ($frontMatterOverride !== '') {
            $frontMatter .= rtrim($frontMatterOverride) . "\n";
        }

        file_put_contents(
            $this->tempDir . '/' . $filename,
            "---\n{$frontMatter}---\n\n# Backend Developer (`Бэкендер`)\n\n**Цель:** Реализовать серверную логику.\n",
        );
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
