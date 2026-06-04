<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Console;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

#[CoversNothing]
final class ValidateConnectivityCommandTest extends TestCase
{
    private string $projectRoot;
    private string $tempDir;
    private string $agentPath;
    private string $argvLogPath;
    private string $promptLogPath;
    private string $stdinLogPath;

    #[Override]
    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
        $this->tempDir = sys_get_temp_dir() . '/validate-connectivity-command-' . bin2hex(random_bytes(6));
        $this->agentPath = $this->tempDir . '/fake-agent.php';
        $this->argvLogPath = $this->tempDir . '/argv.jsonl';
        $this->promptLogPath = $this->tempDir . '/prompts.jsonl';
        $this->stdinLogPath = $this->tempDir . '/stdin.log';

        mkdir($this->tempDir, 0777, true);
        $this->writeFakeAgent();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function dryRunPrintsRolesAndCommandsWithoutStartingProcess(): void
    {
        $configPath = $this->writeConfig([
            'ok_role' => [
                PHP_BINARY,
                $this->agentPath,
                'ok',
                '--system-prompt',
                '@system-prompt',
                '--append-system-prompt',
                '@append-system-prompt',
            ],
        ]);

        $process = $this->runCommand(['--config', $configPath, '--dry-run']);

        self::assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
        self::assertStringContainsString('ok_role', $process->getOutput());
        self::assertStringContainsString('DRY RUN', $process->getOutput());
        self::assertStringContainsString($this->agentPath, $process->getOutput());
        self::assertStringContainsString('Ответь ровно ok без Markdown.', $process->getOutput());
        self::assertStringNotContainsString('@system-prompt', $process->getOutput());
        self::assertStringNotContainsString('@append-system-prompt', $process->getOutput());
        self::assertFileDoesNotExist($this->stdinLogPath);
        self::assertFileDoesNotExist($this->argvLogPath);
    }

    #[Test]
    public function passesResolvedPromptsAndUserPromptThroughArgv(): void
    {
        $configPath = $this->writeConfig([
            'ok_role' => [
                PHP_BINARY,
                $this->agentPath,
                'check-argv',
                '--system-prompt',
                '@system-prompt',
                '--append-system-prompt',
                '@append-system-prompt',
            ],
        ]);

        $process = $this->runCommand(['--config', $configPath]);

        self::assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
        self::assertStringContainsString('ok_role', $process->getOutput());
        self::assertStringContainsString('OK', $process->getOutput());
        self::assertSame('', trim((string) file_get_contents($this->stdinLogPath)));

        /** @var list<string> $argv */
        $argv = json_decode(trim((string) file_get_contents($this->argvLogPath)), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotContains('@system-prompt', $argv);
        self::assertNotContains('@append-system-prompt', $argv);
        self::assertSame('Ответь ровно ok без Markdown.', $argv[array_key_last($argv)]);

        /** @var array{system_path: string, append_path: string, system_exists: bool, append_exists: bool} $promptLog */
        $promptLog = json_decode(trim((string) file_get_contents($this->promptLogPath)), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($promptLog['system_exists']);
        self::assertTrue($promptLog['append_exists']);
        self::assertFileDoesNotExist($promptLog['system_path']);
        self::assertFileDoesNotExist($promptLog['append_path']);
    }

    #[Test]
    public function returnsFailureForEmptyOutputAndNonZeroExitCode(): void
    {
        $configPath = $this->writeConfig([
            'empty_role' => [PHP_BINARY, $this->agentPath, 'empty'],
            'fail_role' => [PHP_BINARY, $this->agentPath, 'fail'],
        ]);

        $process = $this->runCommand(['--config', $configPath]);

        self::assertFalse($process->isSuccessful());
        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('empty output', $process->getOutput());
        self::assertStringContainsString('exit code 2: boom', $process->getOutput());
    }

    #[Test]
    public function filtersByRole(): void
    {
        $configPath = $this->writeConfig([
            'ok_role' => [PHP_BINARY, $this->agentPath, 'ok'],
            'fail_role' => [PHP_BINARY, $this->agentPath, 'fail'],
        ]);

        $process = $this->runCommand(['--config', $configPath, '--role', 'ok_role']);

        self::assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
        self::assertStringContainsString('ok_role', $process->getOutput());
        self::assertStringNotContainsString('fail_role', $process->getOutput());
    }

    #[Test]
    public function returnsFailureOnTimeout(): void
    {
        $configPath = $this->writeConfig([
            'slow_role' => [PHP_BINARY, $this->agentPath, 'slow'],
        ]);

        $process = $this->runCommand(['--config', $configPath, '--timeout', '1']);

        self::assertFalse($process->isSuccessful());
        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('slow_role', $process->getOutput());
        self::assertStringContainsString('timeout', $process->getOutput());
    }

    /**
     * @param array<string, list<string>> $roles
     */
    private function writeConfig(array $roles): string
    {
        $configPath = $this->tempDir . '/chains.yaml';
        $config = ['roles' => []];

        foreach ($roles as $roleName => $command) {
            $config['roles'][$roleName] = ['command' => $command];
        }

        file_put_contents($configPath, Yaml::dump($config, 4, 2));

        return $configPath;
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments): Process
    {
        $process = new Process(array_merge([PHP_BINARY, 'bin/task-orchestrator', 'validate:connectivity'], $arguments), $this->projectRoot);
        $process->setTimeout(20);
        $process->run();

        return $process;
    }

    private function writeFakeAgent(): void
    {
        file_put_contents($this->agentPath, <<<'PHP'
<?php

declare(strict_types=1);

$mode = $argv[1] ?? 'ok';
$stdin = stream_get_contents(STDIN);
file_put_contents(__DIR__ . '/stdin.log', $stdin . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/argv.jsonl', json_encode(array_slice($argv, 1), JSON_THROW_ON_ERROR) . "\n", FILE_APPEND);

$systemPromptPath = findArgumentValue($argv, '--system-prompt');
$appendPromptPath = findArgumentValue($argv, '--append-system-prompt');
if ($systemPromptPath !== null || $appendPromptPath !== null) {
    file_put_contents(__DIR__ . '/prompts.jsonl', json_encode([
        'system_path' => $systemPromptPath,
        'append_path' => $appendPromptPath,
        'system_exists' => is_string($systemPromptPath) && is_file($systemPromptPath),
        'append_exists' => is_string($appendPromptPath) && is_file($appendPromptPath),
    ], JSON_THROW_ON_ERROR) . "\n", FILE_APPEND);
}

if ($mode === 'slow') {
    sleep(2);
}

if ($mode === 'check-argv') {
    $argvLine = implode("\n", $argv);
    if (str_contains($argvLine, '@system-prompt') || str_contains($argvLine, '@append-system-prompt')) {
        fwrite(STDERR, 'unresolved placeholder');
        exit(3);
    }

    if (($argv[array_key_last($argv)] ?? null) !== 'Ответь ровно ok без Markdown.') {
        fwrite(STDERR, 'missing user prompt argv');
        exit(4);
    }

    if (!is_string($systemPromptPath) || !is_file($systemPromptPath)) {
        fwrite(STDERR, 'missing system prompt file');
        exit(5);
    }

    if (!is_string($appendPromptPath) || !is_file($appendPromptPath)) {
        fwrite(STDERR, 'missing append prompt file');
        exit(6);
    }
}

if ($mode === 'empty') {
    exit(0);
}

if ($mode === 'fail') {
    fwrite(STDERR, 'boom');
    exit(2);
}

echo "ok\n";

function findArgumentValue(array $argv, string $name): ?string
{
    $index = array_search($name, $argv, true);
    if (!is_int($index)) {
        return null;
    }

    $value = $argv[$index + 1] ?? null;

    return is_string($value) ? $value : null;
}
PHP);
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
