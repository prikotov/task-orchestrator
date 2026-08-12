<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Bin;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Проверяет безопасный контракт bin/agent-push без сети и реальных секретов.
 */
#[CoversNothing]
final class AgentPushScriptTest extends TestCase
{
    private const SYNTHETIC_TOKEN = 'synthetic-installation-token';

    private string $projectRoot;
    private string $sandbox;

    #[Override]
    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
        $this->sandbox = sys_get_temp_dir() . '/agent-push-' . bin2hex(random_bytes(6));

        mkdir($this->sandbox . '/bin', 0700, true);
        copy($this->projectRoot . '/bin/agent-push', $this->sandbox . '/bin/agent-push');
        chmod($this->sandbox . '/bin/agent-push', 0700);
        $this->writeExecutable('bin/console', <<<'BASH'
#!/usr/bin/env bash
if [[ -n "${TOKEN_COMMAND_CAPTURE:-}" ]]; then
    : > "$TOKEN_COMMAND_CAPTURE"
fi
if [[ "$*" != 'agent:token acme/example --format=plain' ]]; then
    echo 'unexpected token command' >&2
    exit 41
fi
printf 'synthetic-installation-token\n'
BASH);
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->sandbox);
    }

    #[Test]
    public function pushesCurrentBranchWithIsolatedBotCredentialsAndProxy(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'task/example');
        $process = $this->runScript(
            ['acme/example', 'task/example'],
            [
                'CODEX_HTTP_PROXY' => 'http://proxy.example.test:8080',
                'GH_TOKEN' => 'owner-token',
                'GITHUB_TOKEN' => 'owner-token',
                'GIT_CONFIG_COUNT' => '1',
                'GIT_CONFIG_KEY_0' => 'credential.helper',
                'GIT_CONFIG_VALUE_0' => 'owner-helper',
                'GIT_CONFIG_PARAMETERS' => "'core.hooksPath'='.git/hooks' 'http.https://github.com/.extraHeader'='Authorization: Basic owner'",
                'GIT_CONFIG' => '/tmp/owner-git-config',
            ],
        );

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringNotContainsString(self::SYNTHETIC_TOKEN, $process->getOutput());
        self::assertStringNotContainsString(self::SYNTHETIC_TOKEN, $process->getErrorOutput());

        $captured = file_get_contents($capture);
        self::assertIsString($captured);
        self::assertStringContainsString(
            'ARGS=push https://github.com/acme/example.git HEAD:refs/heads/task/example',
            $captured,
        );
        self::assertStringContainsString('AUTH_OK=1', $captured);
        self::assertStringContainsString('GIT_CONFIG_GLOBAL=/dev/null', $captured);
        self::assertStringContainsString('GIT_CONFIG_NOSYSTEM=1', $captured);
        self::assertStringContainsString('CREDENTIAL_HELPER=', $captured);
        self::assertStringContainsString('CREDENTIAL_INTERACTIVE=never', $captured);
        self::assertStringContainsString('HTTPS_PROXY=http://proxy.example.test:8080', $captured);
        self::assertStringContainsString('HTTP_PROXY=http://proxy.example.test:8080', $captured);
        self::assertStringContainsString('GIT_TERMINAL_PROMPT=0', $captured);
        self::assertStringContainsString('GH_TOKEN_UNSET=1', $captured);
        self::assertStringContainsString('GITHUB_TOKEN_UNSET=1', $captured);
        self::assertStringContainsString('GIT_CONFIG_COUNT=6', $captured);
        self::assertStringContainsString('GIT_CONFIG_PARAMETERS_UNSET=1', $captured);
        self::assertStringContainsString('GIT_CONFIG_UNSET=1', $captured);
        self::assertStringContainsString('HOOKS_PATH=/dev/null', $captured);
        self::assertStringContainsString('EXTRA_HEADER_RESET=1', $captured);
        self::assertStringContainsString('HTTP_VERSION=HTTP/1.1', $captured);
        self::assertStringContainsString('GIT_TRACE2_UNSET=1', $captured);
        self::assertStringContainsString('GIT_TRACE2_EVENT_UNSET=1', $captured);
        self::assertStringContainsString('GIT_TRACE2_CONFIG_PARAMS_UNSET=1', $captured);
        self::assertStringContainsString('GIT_TRACE_REDACT_UNSET=1', $captured);
        self::assertStringContainsString('GIT_TRACE2_ENV_VARS_UNSET=1', $captured);
        self::assertStringContainsString('GIT_TRACE2_REDACT_UNSET=1', $captured);
    }

    #[Test]
    public function failsBeforeObtainingTokenWhenBranchDoesNotMatch(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'task/other');

        $process = $this->runScript(['acme/example', 'task/example']);

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString("current branch is 'task/other'", $process->getErrorOutput());
        self::assertFileDoesNotExist($capture);
    }

    #[Test]
    public function rejectsMalformedRepositoryBeforeRunningGit(): void
    {
        $process = $this->runScript(['git@github.com:owner/repo.git', 'task/example']);

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('repository must have the form', $process->getErrorOutput());
    }

    #[Test]
    public function rejectsMalformedBranchBeforeObtainingToken(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'task/example');

        $process = $this->runScript(['acme/example', 'bad branch']);

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('branch is not a valid Git branch name', $process->getErrorOutput());
        self::assertFileDoesNotExist($capture);
    }

    #[Test]
    public function rejectsProtectedBranchesBeforeObtainingToken(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'main');

        $mainProcess = $this->runScript(['acme/example', 'main']);
        $releaseProcess = $this->runScript(['acme/example', 'release/1.2']);

        self::assertSame(2, $mainProcess->getExitCode());
        self::assertSame(2, $releaseProcess->getExitCode());
        self::assertStringContainsString('protected branches', $mainProcess->getErrorOutput());
        self::assertStringContainsString('protected branches', $releaseProcess->getErrorOutput());
        self::assertFileDoesNotExist($capture);
    }

    #[Test]
    public function rejectsDifferentRepositoryRootBeforeObtainingToken(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        mkdir($this->sandbox . '/another-repository', 0700);
        $this->writeGitFixture($capture, 'task/example', $this->sandbox . '/another-repository');

        $process = $this->runScript(['acme/example', 'task/example']);

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('repository checkout that contains it', $process->getErrorOutput());
        self::assertFileDoesNotExist($capture);
    }

    #[Test]
    public function acceptsHttpsOriginForRequestedRepository(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'task/example', null, self::SYNTHETIC_TOKEN, null, 0, 'https://github.com/acme/example.git');

        $process = $this->runScript(['acme/example', 'task/example']);

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    #[Test]
    public function rejectsRepositoryThatDoesNotMatchSshOriginBeforeObtainingToken(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $tokenCapture = $this->sandbox . '/token-command.called';
        $this->writeGitFixture($capture, 'task/example', null, self::SYNTHETIC_TOKEN, null, 0, 'git@github.com:acme/different.git');

        $process = $this->runScript(
            ['acme/example', 'task/example'],
            ['TOKEN_COMMAND_CAPTURE' => $tokenCapture],
        );

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('remote.origin.url does not match repository', $process->getErrorOutput());
        self::assertFileDoesNotExist($capture);
        self::assertFileDoesNotExist($tokenCapture);
    }

    #[Test]
    public function acceptsNormalizedSshOriginCaseAndSuffix(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'task/example', null, self::SYNTHETIC_TOKEN, null, 0, 'ssh://git@github.com/ACME/Example.git');

        $process = $this->runScript(['acme/example', 'task/example']);

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    #[Test]
    public function bashXtraceDoesNotExposeTokenOrAuthorizationHeader(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'task/example');

        $process = $this->runScript(
            ['acme/example', 'task/example'],
            [],
            ['bash', '-x', $this->sandbox . '/bin/agent-push'],
        );

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringNotContainsString(self::SYNTHETIC_TOKEN, $process->getErrorOutput());
        self::assertStringNotContainsString('Authorization:', $process->getErrorOutput());
    }

    #[Test]
    public function commandScopeDisablesRepositoryHooksBeforePush(): void
    {
        $hookCapture = $this->sandbox . '/hook.env';
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'task/example', null, self::SYNTHETIC_TOKEN, $hookCapture);

        $process = $this->runScript(['acme/example', 'task/example']);

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertFileDoesNotExist($hookCapture);
        self::assertStringNotContainsString(self::SYNTHETIC_TOKEN, $process->getOutput());
        self::assertStringNotContainsString(self::SYNTHETIC_TOKEN, $process->getErrorOutput());
    }

    #[Test]
    public function inheritedTrace2ConfigurationCannotExposeCredentials(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $trace = $this->sandbox . '/git-trace.json';
        $this->writeGitFixture($capture, 'task/example');

        $process = $this->runScript(
            ['acme/example', 'task/example'],
            [
                'GIT_TRACE2' => $trace,
                'GIT_TRACE2_EVENT' => $trace,
                'GIT_TRACE2_PERF' => $trace,
                'GIT_TRACE2_BRIEF' => '1',
                'GIT_TRACE2_CONFIG_PARAMS' => 'http.*',
                'GIT_TRACE_REDACT' => '0',
                'GIT_TRACE2_ENV_VARS' => 'GIT_CONFIG_VALUE_4',
                'GIT_TRACE2_REDACT' => '0',
            ],
        );

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertFileDoesNotExist($trace);
        self::assertStringNotContainsString(self::SYNTHETIC_TOKEN, $process->getErrorOutput());
        self::assertStringNotContainsString('Authorization:', $process->getErrorOutput());
    }

    #[Test]
    public function failsWhenTokenCommandFails(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'task/example');
        $this->writeExecutable('bin/console', <<<'BASH'
#!/usr/bin/env bash
echo 'synthetic-installation-token' >&2
exit 17
BASH);

        $process = $this->runScript(['acme/example', 'task/example']);

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('failed to obtain', $process->getErrorOutput());
        self::assertStringNotContainsString(self::SYNTHETIC_TOKEN, $process->getErrorOutput());
        self::assertFileDoesNotExist($capture);
    }

    #[Test]
    public function rejectsMalformedTokenBeforePush(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'task/example');
        $this->writeExecutable('bin/console', <<<'BASH'
#!/usr/bin/env bash
printf 'malformed token\n'
BASH);

        $process = $this->runScript(['acme/example', 'task/example']);

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('empty or malformed token', $process->getErrorOutput());
        self::assertStringNotContainsString('malformed token', $process->getOutput());
        self::assertFileDoesNotExist($capture);
    }

    #[Test]
    public function acceptsOpaqueTokenWithDotsAndHyphens(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $opaqueToken = 'opaque.token-with-hyphen';
        $this->writeGitFixture($capture, 'task/example', null, $opaqueToken);
        $this->writeExecutable('bin/console', <<<BASH
#!/usr/bin/env bash
printf '%s\\n' '$opaqueToken'
BASH);

        $process = $this->runScript(['acme/example', 'task/example']);

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('AUTH_OK=1', (string) file_get_contents($capture));
    }

    #[Test]
    public function failsForDetachedHeadBeforeObtainingToken(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, null);

        $process = $this->runScript(['acme/example', 'task/example']);

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('HEAD is detached', $process->getErrorOutput());
        self::assertFileDoesNotExist($capture);
    }

    #[Test]
    public function leavesProxyUnsetWhenCodexProxyIsAbsent(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'task/example');

        $process = $this->runScript(
            ['acme/example', 'task/example'],
            [
                'HTTP_PROXY' => false,
                'HTTPS_PROXY' => false,
                'http_proxy' => false,
                'https_proxy' => false,
            ],
        );

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $captured = file_get_contents($capture);
        self::assertIsString($captured);
        self::assertStringContainsString("HTTPS_PROXY=\n", $captured);
        self::assertStringContainsString("HTTP_PROXY=\n", $captured);
    }

    #[Test]
    public function propagatesPushFailureWithoutExposingToken(): void
    {
        $capture = $this->sandbox . '/git-push.env';
        $this->writeGitFixture($capture, 'task/example', null, self::SYNTHETIC_TOKEN, null, 23);

        $process = $this->runScript(['acme/example', 'task/example']);

        self::assertSame(23, $process->getExitCode());
        self::assertStringContainsString('simulated push failure', $process->getErrorOutput());
        self::assertStringNotContainsString(self::SYNTHETIC_TOKEN, $process->getOutput());
        self::assertStringNotContainsString(self::SYNTHETIC_TOKEN, $process->getErrorOutput());
    }

    /**
     * @param list<string> $arguments
     */
    private function runScript(
        array $arguments,
        array $environment = [],
        ?array $command = null,
    ): Process
    {
        $process = new Process(
            array_merge($command ?? [$this->sandbox . '/bin/agent-push'], $arguments),
            $this->sandbox,
            [
                'PATH' => $this->sandbox . '/bin:' . (getenv('PATH') ?: ''),
                'CODEX_HTTP_PROXY' => false,
                'GIT_TRACE' => false,
                'GIT_TRACE_CURL' => false,
                'GIT_CURL_VERBOSE' => false,
                'GIT_TRACE2' => false,
                'GIT_TRACE2_EVENT' => false,
                'GIT_TRACE2_PERF' => false,
                'GIT_TRACE2_BRIEF' => false,
                'GIT_TRACE2_CONFIG_PARAMS' => false,
                'GIT_TRACE_REDACT' => false,
                'GIT_TRACE2_ENV_VARS' => false,
                'GIT_TRACE2_REDACT' => false,
                ...$environment,
            ],
        );
        $process->run();

        return $process;
    }

    private function writeGitFixture(
        string $capture,
        ?string $currentBranch,
        ?string $repositoryRoot = null,
        string $token = self::SYNTHETIC_TOKEN,
        ?string $hookCapture = null,
        int $pushExitCode = 0,
        string $originUrl = 'git@github.com:acme/example.git',
    ): void
    {
        $captureLiteral = escapeshellarg($capture);
        $branchLiteral = escapeshellarg($currentBranch ?? '');
        $rootLiteral = escapeshellarg($repositoryRoot ?? $this->sandbox);
        $hookCaptureLiteral = escapeshellarg($hookCapture ?? '');
        $originUrlLiteral = escapeshellarg($originUrl);
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == 'check-ref-format' ]]; then
    [[ "${2:-}" == '--branch' && -n "${3:-}" && "${3:-}" != *' '* ]]
    exit
fi
if [[ "${1:-}" == 'rev-parse' ]]; then
    printf '%s\n' __ROOT__
    exit
fi
if [[ "${1:-}" == 'symbolic-ref' ]]; then
    [[ -n __BRANCH__ ]] || exit 1
    printf '%s\n' __BRANCH__
    exit
fi
if [[ "${1:-}" == 'config' ]]; then
    [[ "${2:-}" == '--local' && "${3:-}" == '--get' && "${4:-}" == 'remote.origin.url' ]]
    [[ ! -v GIT_CONFIG ]]
    printf '%s\n' __ORIGIN_URL__
    exit
fi
if [[ -n __HOOK_CAPTURE__ && "${GIT_CONFIG_VALUE_2-}" != '/dev/null' ]]; then
    env > __HOOK_CAPTURE__
fi
{
    printf 'ARGS=%s\n' "$*"
    printf 'GIT_CONFIG_GLOBAL=%s\n' "${GIT_CONFIG_GLOBAL-}"
    printf 'GIT_CONFIG_NOSYSTEM=%s\n' "${GIT_CONFIG_NOSYSTEM-}"
    printf 'GIT_CONFIG_COUNT=%s\n' "${GIT_CONFIG_COUNT-}"
    printf 'CREDENTIAL_HELPER=%s\n' "${GIT_CONFIG_VALUE_0-}"
    printf 'CREDENTIAL_INTERACTIVE=%s\n' "${GIT_CONFIG_VALUE_1-}"
    printf 'HOOKS_PATH=%s\n' "${GIT_CONFIG_VALUE_2-}"
    expected_header='Authorization: Basic __EXPECTED_AUTH__'
    effective_headers=('Authorization: Basic owner')
    for header in "${GIT_CONFIG_VALUE_3-}" "${GIT_CONFIG_VALUE_4-}"; do
        if [[ -z "$header" ]]; then
            effective_headers=()
        else
            effective_headers+=("$header")
        fi
    done
    [[ ${#effective_headers[@]} -eq 1 && "${effective_headers[0]}" == "$expected_header" ]]
    printf 'EXTRA_HEADER_RESET=1\n'
    printf 'AUTH_OK=1\n'
    printf 'HTTP_VERSION=%s\n' "${GIT_CONFIG_VALUE_5-}"
    printf 'HTTPS_PROXY=%s\n' "${HTTPS_PROXY-}"
    printf 'HTTP_PROXY=%s\n' "${HTTP_PROXY-}"
    printf 'GIT_TERMINAL_PROMPT=%s\n' "${GIT_TERMINAL_PROMPT-}"
    [[ ! -v GH_TOKEN ]] && printf 'GH_TOKEN_UNSET=1\n'
    [[ ! -v GITHUB_TOKEN ]] && printf 'GITHUB_TOKEN_UNSET=1\n'
    [[ ! -v GIT_CONFIG_PARAMETERS ]] && printf 'GIT_CONFIG_PARAMETERS_UNSET=1\n'
    [[ ! -v GIT_CONFIG ]] && printf 'GIT_CONFIG_UNSET=1\n'
    [[ ! -v GIT_TRACE2 ]] && printf 'GIT_TRACE2_UNSET=1\n'
    [[ ! -v GIT_TRACE2_EVENT ]] && printf 'GIT_TRACE2_EVENT_UNSET=1\n'
    [[ ! -v GIT_TRACE2_CONFIG_PARAMS ]] && printf 'GIT_TRACE2_CONFIG_PARAMS_UNSET=1\n'
    [[ ! -v GIT_TRACE_REDACT ]] && printf 'GIT_TRACE_REDACT_UNSET=1\n'
    [[ ! -v GIT_TRACE2_ENV_VARS ]] && printf 'GIT_TRACE2_ENV_VARS_UNSET=1\n'
    [[ ! -v GIT_TRACE2_REDACT ]] && printf 'GIT_TRACE2_REDACT_UNSET=1\n'
} > __CAPTURE__
if (( __PUSH_EXIT_CODE__ != 0 )); then
    printf 'simulated push failure\n' >&2
    exit __PUSH_EXIT_CODE__
fi
BASH;
        $script = str_replace(
            ['__BRANCH__', '__CAPTURE__', '__ROOT__', '__EXPECTED_AUTH__', '__HOOK_CAPTURE__', '__PUSH_EXIT_CODE__', '__ORIGIN_URL__'],
            [
                $branchLiteral,
                $captureLiteral,
                $rootLiteral,
                base64_encode('x-access-token:' . $token),
                $hookCaptureLiteral,
                (string) $pushExitCode,
                $originUrlLiteral,
            ],
            $script,
        );
        $this->writeExecutable('bin/git', $script);
    }

    private function writeExecutable(string $relativePath, string $content): void
    {
        $path = $this->sandbox . '/' . $relativePath;
        file_put_contents($path, $content . "\n");
        chmod($path, 0700);
    }
}
