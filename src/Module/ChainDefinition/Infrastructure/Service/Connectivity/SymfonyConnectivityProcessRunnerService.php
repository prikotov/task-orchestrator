<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Connectivity;

use Override;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Connectivity\ConnectivityProcessRunnerInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityProcessRequestVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityProcessResultVo;

/**
 * Symfony Process implementation (реализация) проверки запуска роли из chains.yaml через argv array.
 */
final readonly class SymfonyConnectivityProcessRunnerService implements ConnectivityProcessRunnerInterface
{
    public function __construct(
        private string $basePath,
    ) {
    }

    #[Override]
    public function run(ConnectivityProcessRequestVo $request): ConnectivityProcessResultVo
    {
        $start = microtime(true);
        $process = new Process($request->getCommand(), $this->resolveWorkingDirectory());
        $process->setTimeout($request->getTimeout());

        if ($request->getStdinPrompt() !== null) {
            $process->setInput($request->getStdinPrompt());
        }

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return new ConnectivityProcessResultVo(
                exitCode: 1,
                stdout: $process->getOutput(),
                stderr: $process->getErrorOutput(),
                durationSeconds: microtime(true) - $start,
                timedOut: true,
            );
        }

        return new ConnectivityProcessResultVo(
            exitCode: $process->getExitCode() ?? 1,
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
            durationSeconds: microtime(true) - $start,
        );
    }

    private function resolveWorkingDirectory(): ?string
    {
        return is_dir($this->basePath) ? $this->basePath : null;
    }
}
