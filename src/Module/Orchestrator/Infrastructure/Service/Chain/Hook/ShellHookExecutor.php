<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain\Hook;

use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Hook\HookExecutorInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\HookResultVo;

/**
 * Выполняет post_step hook через Symfony Process.
 *
 * Запускает shell-скрипт с таймаутом 30 секунд.
 * Hook failure = warning в лог, не прерывает цепочку.
 * stdout/stderr логируются через LoggerInterface.
 */
final readonly class ShellHookExecutor implements HookExecutorInterface
{
    /** @var int Таймаут выполнения hook в секундах */
    private const int HOOK_TIMEOUT = 30;

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[Override]
    public function execute(string $scriptPath, array $context = []): HookResultVo
    {
        $startTime = microtime(true);

        $this->logger->info('Hook execution started', [
            'script' => $scriptPath,
            'context' => $context,
        ]);

        try {
            $envVars = $this->buildEnvVars($context);

            $process = new Process(['sh', $scriptPath]);
            $process->setTimeout(self::HOOK_TIMEOUT);
            $process->setEnv($envVars);

            $process->run();

            $duration = microtime(true) - $startTime;
            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();
            $exitCode = $process->getExitCode() ?? -1;

            // Логируем stdout/stderr
            if ($stdout !== '') {
                $this->logger->info('Hook stdout', [
                    'script' => $scriptPath,
                    'stdout' => $stdout,
                ]);
            }

            if ($stderr !== '') {
                $this->logger->warning('Hook stderr', [
                    'script' => $scriptPath,
                    'stderr' => $stderr,
                ]);
            }

            if ($exitCode !== 0) {
                $this->logger->warning('Hook failed with non-zero exit code', [
                    'script' => $scriptPath,
                    'exitCode' => $exitCode,
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'duration' => $duration,
                ]);

                return HookResultVo::warning(
                    command: $scriptPath,
                    exitCode: $exitCode,
                    stdout: $stdout,
                    stderr: $stderr,
                    duration: $duration,
                    timedOut: false,
                    reason: sprintf('Hook exited with code %d.', $exitCode),
                );
            }

            $this->logger->info('Hook executed successfully', [
                'script' => $scriptPath,
                'duration' => $duration,
            ]);

            return HookResultVo::success(
                command: $scriptPath,
                stdout: $stdout,
                stderr: $stderr,
                duration: $duration,
            );
        } catch (ProcessTimedOutException $e) {
            $duration = microtime(true) - $startTime;

            $this->logger->warning('Hook timed out', [
                'script' => $scriptPath,
                'timeout' => self::HOOK_TIMEOUT,
                'duration' => $duration,
            ]);

            return HookResultVo::warning(
                command: $scriptPath,
                exitCode: -1,
                stdout: '',
                stderr: $e->getMessage(),
                duration: $duration,
                timedOut: true,
                reason: sprintf('Hook timed out after %d seconds.', self::HOOK_TIMEOUT),
            );
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            $this->logger->warning('Hook execution failed with exception', [
                'script' => $scriptPath,
                'exception' => $e->getMessage(),
                'duration' => $duration,
            ]);

            return HookResultVo::warning(
                command: $scriptPath,
                exitCode: -1,
                stdout: '',
                stderr: $e->getMessage(),
                duration: $duration,
                timedOut: false,
                reason: sprintf('Hook execution failed: %s', $e->getMessage()),
            );
        }
    }

    /**
     * Строит массив env-переменных из контекста.
     *
     * Ключи контекста преобразуются в HOOK_*(uppercase) env vars:
     *   chain_name → HOOK_CHAIN_NAME
     *   step_name  → HOOK_STEP_NAME
     *   runner     → HOOK_RUNNER
     *   exit_code  → HOOK_EXIT_CODE
     *   duration   → HOOK_DURATION
     *   role       → HOOK_ROLE
     *
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private function buildEnvVars(array $context): array
    {
        $envVars = [];

        foreach ($context as $key => $value) {
            if ($value === null) {
                continue;
            }

            $envKey = 'HOOK_' . strtoupper($key);
            $envVars[$envKey] = is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $envVars;
    }
}
