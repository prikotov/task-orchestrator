<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Bin;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Интеграционная проверка безопасной публикации PR-ветки через `bin/push-pr-branch`.
 *
 * Helper — единственный источник истины (source of truth) для push PR-ветки
 * installation token'ом GitHub App. Здесь проверяется публичный контракт скрипта:
 *   - fail-fast на аргументах (обязательный `<owner>/<repo>`, некорректный slug);
 *   - запрет detached HEAD и целевых веток (main/master/release/*);
 *   - безопасное получение токена только через `bin/console agent:token ... --format=plain`
 *     в command substitution без вывода в сессию;
 *   - изоляция Git: ровно 7 пар GIT_CONFIG_KEY/VALUE, http.proxyAuthMethod=basic,
 *     HTTP/1.1, пустые credential.helper/extraHeader, отключённые hooks;
 *   - прокси НЕ отключается (env прокси проходит сквозь без изменений);
 *   - токен никогда не попадает в stdout/stderr процесса helper'а.
 *
 * Реальный GitHub и настоящие секреты не используются: helper запускается в
 * изолированном временном проекте с безопасными fake `bin/console` и `git`.
 * Фиктивный токен — только fixture внутри теста, во внешний мир не утекает.
 */
#[CoversNothing]
final class PushPrBranchScriptTest extends TestCase
{
    private const string FAKE_TOKEN = 'test_fixture_token';

    private const string REPO_SLUG = 'octocat/Hello-World';

    private const string FAKE_PROXY = 'http://127.0.0.1:3128';

    private string $projectRoot;

    private string $tempDir;

    private Filesystem $filesystem;

    #[Override]
    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
        $this->tempDir = sys_get_temp_dir() . '/push-pr-branch-test-' . bin2hex(random_bytes(6));
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->tempDir . '/bin');
        $this->filesystem->mkdir($this->tempDir . '/fake-bin');

        // Source of truth — копия проверяемого helper'а во временном проекте,
        // чтобы ROOT_DIR helper'а разрешался в tempDir (fake bin/console).
        $this->filesystem->copy(
            $this->projectRoot . '/bin/push-pr-branch',
            $this->tempDir . '/bin/push-pr-branch',
        );
        $this->filesystem->chmod($this->tempDir . '/bin/push-pr-branch', 0755);

        $this->writeFakeConsole();
        $this->writeFakeGit();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    #[Test]
    public function happyPathPushesCurrentBranchWithIsolatedBasicAuthProxyConfig(): void
    {
        // Arrange: корректный slug, ветка task/example, фиктивный токен.
        $recordFile = $this->tempDir . '/push-record.txt';
        $consoleArgsFile = $this->tempDir . '/console-args.txt';

        // Act
        $process = $this->runHelper([self::REPO_SLUG], $recordFile, $consoleArgsFile, 'task/example');

        // Assert: push завершён успешно.
        self::assertTrue(
            $process->isSuccessful(),
            'stdout: ' . $process->getOutput() . "\nstderr: " . $process->getErrorOutput(),
        );

        // token-команда вызвана ровно с ожидаемыми аргументами (source of truth).
        $consoleArgs = (string) file_get_contents($consoleArgsFile);
        self::assertStringContainsString('CONSOLE_ARGC=3', $consoleArgs);
        self::assertStringContainsString('CONSOLE_ARGV_0=agent:token', $consoleArgs);
        self::assertStringContainsString('CONSOLE_ARGV_1=' . self::REPO_SLUG, $consoleArgs);
        self::assertStringContainsString('CONSOLE_ARGV_2=--format=plain', $consoleArgs);

        $record = (string) file_get_contents($recordFile);

        // URL/refspec построены из slug и текущей ветки.
        $this->assertRecordContainsArgument($record, 'https://github.com/' . self::REPO_SLUG . '.git');
        $this->assertRecordContainsArgument($record, 'HEAD:refs/heads/task/example');

        // Ровно 7 пар GIT_CONFIG_KEY/VALUE.
        self::assertStringContainsString('GIT_CONFIG_COUNT=7', $record);
        for ($index = 0; $index <= 6; ++$index) {
            self::assertStringContainsString('GIT_CONFIG_KEY_' . $index . '=', $record, "missing KEY_$index");
            self::assertStringContainsString('GIT_CONFIG_VALUE_' . $index . '=', $record, "missing VALUE_$index");
        }

        // Точные значения изоляции Git.
        $this->assertRecordEquals($record, 'GIT_CONFIG_KEY_0', 'credential.helper');
        $this->assertRecordEquals($record, 'GIT_CONFIG_VALUE_0', '');
        $this->assertRecordEquals($record, 'GIT_CONFIG_KEY_1', 'credential.interactive');
        $this->assertRecordEquals($record, 'GIT_CONFIG_VALUE_1', 'never');
        $this->assertRecordEquals($record, 'GIT_CONFIG_KEY_2', 'core.hooksPath');
        $this->assertRecordEquals($record, 'GIT_CONFIG_VALUE_2', '/dev/null');
        $this->assertRecordEquals($record, 'GIT_CONFIG_KEY_3', 'http.https://github.com/.extraHeader');
        $this->assertRecordEquals($record, 'GIT_CONFIG_VALUE_3', '');
        $this->assertRecordEquals($record, 'GIT_CONFIG_KEY_4', 'http.https://github.com/.extraHeader');
        $expectedAuth = 'Authorization: Basic ' . base64_encode('x-access-token:' . self::FAKE_TOKEN);
        $this->assertRecordEquals($record, 'GIT_CONFIG_VALUE_4', $expectedAuth);
        $this->assertRecordEquals($record, 'GIT_CONFIG_KEY_5', 'http.version');
        $this->assertRecordEquals($record, 'GIT_CONFIG_VALUE_5', 'HTTP/1.1');
        $this->assertRecordEquals($record, 'GIT_CONFIG_KEY_6', 'http.proxyAuthMethod');
        $this->assertRecordEquals($record, 'GIT_CONFIG_VALUE_6', 'basic');

        // Изоляция конфига и интерактивности.
        $this->assertRecordEquals($record, 'GIT_CONFIG_GLOBAL', '/dev/null');
        $this->assertRecordEquals($record, 'GIT_CONFIG_NOSYSTEM', '1');
        $this->assertRecordEquals($record, 'GIT_ASKPASS', '/bin/false');
        $this->assertRecordEquals($record, 'GIT_TERMINAL_PROMPT', '0');

        // Прокси НЕ отключён: env прокси проходит сквозь helper без изменений.
        $this->assertRecordEquals($record, 'HTTP_PROXY', self::FAKE_PROXY);
        $this->assertRecordEquals($record, 'HTTPS_PROXY', self::FAKE_PROXY);
        self::assertStringNotContainsString('GIT_CONFIG_KEY=http.proxy', $record);

        // Токен никогда не попадает в stdout/stderr процесса helper'а.
        $this->assertTokenNotLeaked($process);
    }

    #[Test]
    public function refusesWithoutArgument(): void
    {
        // Act
        $process = $this->runHelper([]);

        // Assert
        self::assertSame(64, $process->getExitCode());
        self::assertStringContainsString('Usage: bin/push-pr-branch <owner>/<repo>', $process->getErrorOutput());
        $this->assertTokenNotLeaked($process);
    }

    #[Test]
    #[DataProvider('invalidSlugs')]
    public function refusesInvalidRepositorySlug(string $slug): void
    {
        // Act
        $process = $this->runHelper([$slug]);

        // Assert
        self::assertSame(64, $process->getExitCode());
        self::assertStringContainsString('invalid repository slug', $process->getErrorOutput());
        $this->assertTokenNotLeaked($process);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSlugs(): iterable
    {
        yield 'no slash' => ['not-a-slug'];
        yield 'double slash' => ['owner//repo'];
        yield 'empty owner' => ['/repo'];
        yield 'empty repo' => ['owner/'];
        yield 'space in owner' => ['own er/repo'];
        yield 'pipe injection' => ['owner/repo;echo pwned'];
        yield 'trailing slash segment' => ['owner/repo/extra'];
    }

    #[Test]
    public function refusesDetachedHead(): void
    {
        // Act
        $process = $this->runHelper([self::REPO_SLUG], null, null, 'HEAD');

        // Assert
        self::assertSame(64, $process->getExitCode());
        self::assertStringContainsString('detached HEAD', $process->getErrorOutput());
        $this->assertTokenNotLeaked($process);
    }

    #[Test]
    #[DataProvider('protectedBranches')]
    public function refusesProtectedTargetBranch(string $branch): void
    {
        // Act
        $process = $this->runHelper([self::REPO_SLUG], null, null, $branch);

        // Assert
        self::assertSame(64, $process->getExitCode());
        self::assertStringContainsString('refusing to push protected branch', $process->getErrorOutput());
        self::assertStringContainsString($branch, $process->getErrorOutput());
        $this->assertTokenNotLeaked($process);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function protectedBranches(): iterable
    {
        yield 'main' => ['main'];
        yield 'master' => ['master'];
        yield 'release branch' => ['release/1.2.3'];
    }

    #[Test]
    #[DataProvider('unsafeTokens')]
    public function abortsWhenTokenIsUnsafeAndNeverPrintsIt(string $unsafeToken): void
    {
        // Arrange / Act
        $process = $this->runHelper(
            [self::REPO_SLUG],
            null,
            null,
            'task/example',
            $unsafeToken,
        );

        // Assert: helper падает до push. Недопустимый токен (если не пустой)
        // не должен попасть в вывод процесса.
        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('installation token', $process->getErrorOutput());
        if ($unsafeToken !== '') {
            self::assertStringNotContainsString($unsafeToken, $process->getOutput());
            self::assertStringNotContainsString($unsafeToken, $process->getErrorOutput());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'space inside' => ['unsafe_bad token'];
        yield 'shell metachar' => ['unsafe_token;echo'];
        yield 'substitution' => ['unsafe_$(id)'];
        yield 'newline' => ["unsafe_token\nextra"];
    }

    #[Test]
    public function propagatesGitPushExitCode(): void
    {
        // Arrange: fake git завершается с кодом 3 на push (через env).

        // Act
        $process = $this->runHelper([self::REPO_SLUG], null, null, 'task/example', self::FAKE_TOKEN, 3);

        // Assert: helper пробрасывает exit code git push без потери.
        self::assertSame(3, $process->getExitCode());
        $this->assertTokenNotLeaked($process);
    }

    #[Test]
    public function sanitizesEnvBeforeTokenFetch(): void
    {
        // Arrange: fake console records env state for security verification.
        $consoleArgsFile = $this->tempDir . '/console-args.txt';

        // Act: run with bash -x + GIT_TRACE/GIT_CURL_VERBOSE sentinels.
        $process = $this->runHelper(
            [self::REPO_SLUG],
            null,
            $consoleArgsFile,
            'task/example',
            self::FAKE_TOKEN,
            0,
            true, // $withXtrace: wraps in bash -x, injects sentinels
        );

        // Assert: push succeeded.
        self::assertTrue(
            $process->isSuccessful(),
            'stdout: ' . $process->getOutput() . "\nstderr: " . $process->getErrorOutput(),
        );

        // Token never leaked to stdout/stderr.
        $this->assertTokenNotLeaked($process);

        // Assert: fake console observed sanitized environment.
        $consoleArgs = (string) file_get_contents($consoleArgsFile);
        self::assertStringContainsString(
            'ENV_XTRACE=0',
            $consoleArgs,
            'xtrace must be off before console subprocess',
        );
        self::assertStringContainsString(
            'ENV_GIT_CURL_VERBOSE=<unset>',
            $consoleArgs,
            'GIT_CURL_VERBOSE must be unset before console subprocess',
        );
        self::assertStringContainsString(
            'ENV_GIT_TRACE=<unset>',
            $consoleArgs,
            'GIT_TRACE sentinel must be unset before console subprocess',
        );
    }

    /**
     * @param list<string> $arguments
     */
    private function runHelper(
        array $arguments,
        ?string $pushRecordFile = null,
        ?string $consoleArgsFile = null,
        string $fakeBranch = 'task/example',
        ?string $fakeToken = self::FAKE_TOKEN,
        int $fakePushExitCode = 0,
        bool $withXtrace = false,
    ): Process {
        $env = [
            // fake git должен опережать системный git.
            'PATH' => $this->tempDir . '/fake-bin:' . getenv('PATH'),
            'PUSH_PR_BRANCH_FAKE_BRANCH' => $fakeBranch,
            'PUSH_PR_BRANCH_FAKE_TOKEN' => $fakeToken ?? '',
            'PUSH_PR_BRANCH_FAKE_PUSH_EXIT' => (string) $fakePushExitCode,
            // Прокси задаём искусственно — чтобы убедиться, что helper его не отключает.
            'HTTP_PROXY' => self::FAKE_PROXY,
            'HTTPS_PROXY' => self::FAKE_PROXY,
        ];

        if ($withXtrace) {
            $env['GIT_TRACE'] = 'sanitization-test-sentinel';
            $env['GIT_CURL_VERBOSE'] = 'sanitization-test-sentinel';
        }

        if ($pushRecordFile !== null) {
            $env['PUSH_PR_BRANCH_PUSH_RECORD_FILE'] = $pushRecordFile;
        }
        if ($consoleArgsFile !== null) {
            $env['PUSH_PR_BRANCH_CONSOLE_ARGS_FILE'] = $consoleArgsFile;
        }

        $command = array_merge([$this->tempDir . '/bin/push-pr-branch'], $arguments);
        if ($withXtrace) {
            $command = array_merge(['bash', '-x'], $command);
        }

        $process = new Process(
            $command,
            $this->tempDir,
            $env,
        );
        $process->setTimeout(20);
        $process->run();

        return $process;
    }

    private function assertTokenNotLeaked(Process $process): void
    {
        self::assertStringNotContainsString(self::FAKE_TOKEN, $process->getOutput(), 'token leaked to stdout');
        self::assertStringNotContainsString(self::FAKE_TOKEN, $process->getErrorOutput(), 'token leaked to stderr');
    }

    private function assertRecordContainsArgument(string $record, string $argument): void
    {
        // Запись аргументов ведётся как `  [<arg>]` по одному на строку.
        self::assertStringContainsString('  [' . $argument . ']', $record);
    }

    private function assertRecordEquals(string $record, string $name, string $expected): void
    {
        self::assertStringContainsString($name . '=' . $expected, $record);
    }

    private function writeFakeConsole(): void
    {
        // Имитирует `bin/console agent:token <slug> --format=plain`: пишет аргументы в
        // диагностический файл (для проверки токен-команды) и печатает фиктивный токен
        // в stdout — ровно как реальная команда в формате plain.
        $script = <<<'BASH'
#!/usr/bin/env bash
# fake bin/console for push-pr-branch integration test (no real secrets).
if [[ -n "${PUSH_PR_BRANCH_CONSOLE_ARGS_FILE:-}" ]]; then
    {
        printf 'CONSOLE_ARGC=%s\n' "$#"
        i=0
        for a in "$@"; do
            printf 'CONSOLE_ARGV_%s=%s\n' "$i" "$a"
            i=$((i + 1))
        done
        # Env sanitization state — recorded so tests can assert the helper
        # turned off xtrace, unset GIT_CURL_VERBOSE and GIT_TRACE* before
        # spawning this console process.
        if [[ ":${SHELLOPTS-}:" == *:xtrace:* ]]; then
            printf 'ENV_XTRACE=1\n'
        else
            printf 'ENV_XTRACE=0\n'
        fi
        printf 'ENV_GIT_CURL_VERBOSE=%s\n' "${GIT_CURL_VERBOSE:-<unset>}"
        printf 'ENV_GIT_TRACE=%s\n' "${GIT_TRACE:-<unset>}"
    } > "${PUSH_PR_BRANCH_CONSOLE_ARGS_FILE}"
fi
printf '%s\n' "${PUSH_PR_BRANCH_FAKE_TOKEN:-}"
BASH;
        $this->filesystem->dumpFile($this->tempDir . '/bin/console', $script);
        $this->filesystem->chmod($this->tempDir . '/bin/console', 0755);
    }

    private function writeFakeGit(): void
    {
        // Имитирует только `git rev-parse --abbrev-ref HEAD` и `git push ...`: для
        // rev-parse возвращает заданную ветку, для push — записывает аргументы и
        // изоляционную конфигурацию окружения в файл и завершается заданным кодом.
        // single-quoted heredoc: bash-переменные остаются как есть, exit-код
        // push задаётся через env PUSH_PR_BRANCH_FAKE_PUSH_EXIT (без sprintf,
        // чтобы множественные %s/%d из bash printf не ломали форматирование).
        $script = <<<'BASH'
#!/usr/bin/env bash
# fake git for push-pr-branch integration test (no network, no real secrets).
case "$1" in
    rev-parse)
        printf '%s\n' "${PUSH_PR_BRANCH_FAKE_BRANCH:-task/example}"
        exit 0
        ;;
    push)
        if [[ -n "${PUSH_PR_BRANCH_PUSH_RECORD_FILE:-}" ]]; then
            {
                printf 'ARGV:\n'
                for a in "$@"; do
                    printf '  [%s]\n' "$a"
                done
                printf 'GIT_CONFIG_COUNT=%s\n' "${GIT_CONFIG_COUNT-<unset>}"
                for i in 0 1 2 3 4 5 6; do
                    key_name="GIT_CONFIG_KEY_$i"
                    val_name="GIT_CONFIG_VALUE_$i"
                    if [[ -v "$key_name" ]]; then
                        k="${!key_name}"
                    else
                        k='<unset>'
                    fi
                    if [[ -v "$val_name" ]]; then
                        v="${!val_name}"
                    else
                        v='<unset>'
                    fi
                    printf 'GIT_CONFIG_KEY_%s=%s\n' "$i" "$k"
                    printf 'GIT_CONFIG_VALUE_%s=%s\n' "$i" "$v"
                done
                printf 'GIT_CONFIG_GLOBAL=%s\n' "${GIT_CONFIG_GLOBAL-<unset>}"
                printf 'GIT_CONFIG_NOSYSTEM=%s\n' "${GIT_CONFIG_NOSYSTEM-<unset>}"
                printf 'GIT_ASKPASS=%s\n' "${GIT_ASKPASS-<unset>}"
                printf 'GIT_TERMINAL_PROMPT=%s\n' "${GIT_TERMINAL_PROMPT-<unset>}"
                printf 'HTTP_PROXY=%s\n' "${HTTP_PROXY-<unset>}"
                printf 'HTTPS_PROXY=%s\n' "${HTTPS_PROXY-<unset>}"
            } >> "${PUSH_PR_BRANCH_PUSH_RECORD_FILE}"
        fi
        exit "${PUSH_PR_BRANCH_FAKE_PUSH_EXIT:-0}"
        ;;
    *)
        printf 'fake git does not handle: %s\n' "$*" >&2
        exit 1
        ;;
esac
BASH;
        $this->filesystem->dumpFile($this->tempDir . '/fake-bin/git', $script);
        $this->filesystem->chmod($this->tempDir . '/fake-bin/git', 0755);
    }
}
