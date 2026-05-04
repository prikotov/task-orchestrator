<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\QualityGate;

use Override;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\QualityGateRunnerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionQualityGateVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\QualityGateResultVo;

/**
 * Выполнение quality gate через Symfony Process.
 */
final readonly class QualityGateRunner implements QualityGateRunnerInterface
{
    #[Override]
    public function run(ExecutionQualityGateVo $gate): QualityGateResultVo
    {
        $process = Process::fromShellCommandline($gate->command);
        $process->setTimeout($gate->timeoutSeconds);

        $start = microtime(true);
        $errorOutput = '';

        try {
            $process->run();
        } catch (\Throwable $e) {
            $errorOutput = $e->getMessage();
        }

        $durationMs = (microtime(true) - $start) * 1000.0;

        $output = $process->getOutput() . $process->getErrorOutput();
        if ($errorOutput !== '') {
            $output .= ($output !== '' ? "\n" : '') . $errorOutput;
        }

        return new QualityGateResultVo(
            label: $gate->label,
            passed: $process->isSuccessful(),
            exitCode: $process->getExitCode() ?? 1,
            output: $output,
            durationMs: $durationMs,
        );
    }
}
