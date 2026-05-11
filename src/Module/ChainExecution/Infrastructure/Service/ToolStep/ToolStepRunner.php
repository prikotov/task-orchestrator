<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\ToolStep;

use Override;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ToolStepRunnerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionToolStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ToolStepResultVo;

/**
 * Выполнение tool-шага через Symfony Process.
 */
final readonly class ToolStepRunner implements ToolStepRunnerInterface
{
    #[Override]
    public function run(ExecutionToolStepVo $tool): ToolStepResultVo
    {
        $process = Process::fromShellCommandline($tool->command);
        $process->setTimeout($tool->timeoutSeconds);

        $start = microtime(true);

        try {
            $process->run();
        } catch (\Throwable) {
            // Process timeout or other error — handled below via exit code
        }

        $durationMs = (microtime(true) - $start) * 1000.0;

        return new ToolStepResultVo(
            exitCode: $process->getExitCode() ?? 1,
            stdout: $process->getOutput(),
            success: $process->isSuccessful(),
            durationMs: $durationMs,
        );
    }
}
