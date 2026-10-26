<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Docs\Agents\Skills\RunSubagent;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Интеграционный тест функции apply_proxy_env_defaults скрипта watch-subagent.sh.
 *
 * Функция вырезается из скрипта текстово и исполняется в чистом bash
 * с контролируемым окружением; проверяются экспортированные переменные.
 * Регрессия TASK-fix-run-subagent-local-proxy: без NO_PROXY запросы к
 * локальному ollama (nserv:11434) уходили во внешний прокси из
 * CODEX_HTTP_PROXY и падали с «Connection error».
 */
#[CoversNothing]
final class ApplyProxyEnvDefaultsTest extends TestCase
{
    private const SCRIPT_PATH = __DIR__ . '/../../../../../../docs/agents/skills/run-subagent/scripts/watch-subagent.sh';

    /**
     * @param array<string, string> $env
     *
     * @return array<string, string> экспортированное окружение после вызова функции
     */
    private function callFunction(array $env): array
    {
        $script = (string) file_get_contents(self::SCRIPT_PATH);

        $start = strpos($script, 'apply_proxy_env_defaults() {');
        self::assertNotFalse($start, 'функция apply_proxy_env_defaults не найдена в скрипте');

        $end = strpos($script, "\n}\n", $start);
        self::assertNotFalse($end, 'не найден конец функции apply_proxy_env_defaults');

        $functionBody = substr($script, $start, $end - $start + 3);

        $process = new Process(
            ['bash', '-c', $functionBody . "\napply_proxy_env_defaults\nenv | grep -E '^(HTTP_PROXY|HTTPS_PROXY|http_proxy|https_proxy|NO_PROXY|no_proxy)=' | sort"],
            null,
            $env,
        );
        $process->setTimeout(30);
        $process->run();

        self::assertSame(0, $process->getExitCode(), "bash завершился с ошибкой:\n" . $process->getErrorOutput());

        $result = [];
        foreach (explode("\n", trim($process->getOutput())) as $line) {
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $result[$name] = $value;
        }

        return $result;
    }

    #[Test]
    public function withoutProxyFunctionDoesNothing(): void
    {
        $env = $this->callFunction([
            'PATH' => '/usr/bin:/bin',
            'CODEX_HTTP_PROXY' => '',
            'ALL_PROXY' => '',
            'all_proxy' => '',
        ]);

        self::assertSame([], $env, 'без прокси функция не должна экспортировать ничего');
    }

    #[Test]
    public function withProxyExportsDefaultNoProxy(): void
    {
        $env = $this->callFunction([
            'PATH' => '/usr/bin:/bin',
            'CODEX_HTTP_PROXY' => 'http://proxy.example:8080',
        ]);

        self::assertSame('http://proxy.example:8080', $env['HTTP_PROXY'] ?? null);
        self::assertSame('http://proxy.example:8080', $env['HTTPS_PROXY'] ?? null);
        self::assertSame('http://proxy.example:8080', $env['http_proxy'] ?? null);
        self::assertSame('http://proxy.example:8080', $env['https_proxy'] ?? null);

        $defaultNoProxy = $env['NO_PROXY'] ?? '';
        self::assertNotSame('', $defaultNoProxy, 'дефолтный NO_PROXY обязан проставляться вместе с прокси');
        self::assertSame($defaultNoProxy, $env['no_proxy'] ?? null, 'no_proxy (нижний регистр) должен совпадать с NO_PROXY');

        foreach (['localhost', '127.0.0.1', 'nserv'] as $host) {
            self::assertContains($host, explode(',', $defaultNoProxy), "NO_PROXY должен содержать {$host}");
        }
    }

    #[Test]
    public function userNoProxyIsNotOverridden(): void
    {
        $env = $this->callFunction([
            'PATH' => '/usr/bin:/bin',
            'CODEX_HTTP_PROXY' => 'http://proxy.example:8080',
            'NO_PROXY' => 'my-host.local',
            'no_proxy' => 'my-host.local',
        ]);

        self::assertSame('http://proxy.example:8080', $env['HTTP_PROXY'] ?? null);
        self::assertSame('my-host.local', $env['NO_PROXY'] ?? null, 'пользовательский NO_PROXY не должен перекрываться');
        self::assertSame('my-host.local', $env['no_proxy'] ?? null);
    }
}
