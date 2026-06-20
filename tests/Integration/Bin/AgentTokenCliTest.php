<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Bin;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Integration-тесты CLI bin/agent-token через реальный процесс (proc_open).
 *
 * Все checked-сценарии fail-fast ДО сетевых вызовов — GitHub API не задействован.
 */
#[CoversNothing]
final class AgentTokenCliTest extends TestCase
{
    private string $projectRoot;
    private string $fixtureDir;
    private string $tmpHome;
    private string $configDir;
    private string $envPemPath;
    private string $envAppId;

    #[Override]
    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
        $this->fixtureDir = $this->projectRoot . '/tests/Unit/AgentToken';

        $this->tmpHome = sys_get_temp_dir() . '/agent-token-cli-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmpHome, 0777, true);

        $this->configDir = $this->tmpHome . '/secrets/agent-identity';
        mkdir($this->configDir, 0700, true);

        // Копируем фикстурный PEM с chmod 0600
        $pemSrc = $this->fixtureDir . '/test-private-key.pem';
        $pemDst = $this->configDir . '/private-key.pem';
        copy($pemSrc, $pemDst);
        chmod($pemDst, 0600);

        // Пишем app-id
        file_put_contents($this->configDir . '/app-id', "12345\n");

        // Кешируем пути для env-передачи
        $this->envPemPath = $pemDst;
        $this->envAppId = '12345';
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpHome);
    }

    #[Test]
    public function helpReturnsZeroAndContainsAgentToken(): void
    {
        $process = $this->runAgentToken(['--help']);

        self::assertSame(0, $process->getExitCode());
        self::assertStringContainsString('agent-token', $process->getOutput());
        $this->assertNoSecretsLeaked($process);
    }

    #[Test]
    public function noArgumentsReturnsError(): void
    {
        $process = $this->runAgentToken([]);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('expected exactly one argument', $process->getErrorOutput());
        $this->assertNoSecretsLeaked($process);
    }

    #[Test]
    public function noslashReturnsInvalidFormatError(): void
    {
        $process = $this->runAgentToken(['noslash']);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('Invalid repository format', $process->getErrorOutput());
        $this->assertNoSecretsLeaked($process);
    }

    #[Test]
    public function missingPemReturnsNotFoundError(): void
    {
        // HOME больше не используется — запускаем без AGENT_PRIVATE_KEY_PATH
        $process = $this->runAgentToken(
            ['octocat/Hello-World'],
            home: null,
            extraEnv: ['AGENT_PRIVATE_KEY_PATH' => '', 'AGENT_APP_ID' => ''],
        );

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('PEM private key not found', $process->getErrorOutput());
        $this->assertNoSecretsLeaked($process);
    }

    /**
     * Убеждаемся, что ни stdout, ни stderr не содержат секретов.
     */
    private function assertNoSecretsLeaked(Process $process): void
    {
        $output = $process->getOutput() . $process->getErrorOutput();
        self::assertStringNotContainsString('PRIVATE KEY', $output, 'PEM content leaked to output');
        self::assertStringNotContainsString('ghs_', $output, 'Token prefix leaked to output');
        self::assertStringNotContainsString('Bearer', $output, 'Bearer scheme leaked to output');
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $extraEnv Дополнительные env-переменные
     */
    private function runAgentToken(array $arguments, ?string $home = null, array $extraEnv = []): Process
    {
        $home ??= $this->tmpHome;

        $env = array_merge(
            ['HOME' => $home, 'AGENT_PRIVATE_KEY_PATH' => $this->envPemPath, 'AGENT_APP_ID' => $this->envAppId],
            $extraEnv,
        );

        $process = new Process(
            [PHP_BINARY, $this->projectRoot . '/bin/agent-token', ...$arguments],
            $this->projectRoot,
            $env,
        );
        $process->setTimeout(10);
        $process->run();

        return $process;
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
