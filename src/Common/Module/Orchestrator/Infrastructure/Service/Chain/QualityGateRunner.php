<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\QualityGateRunnerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\QualityGateResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\QualityGateVo;
use Override;
use Symfony\Component\Process\Process;

/**
 * Выполнение quality gate через Symfony Process.
 *
 * Запускает shell-команду из QualityGateVo, ограничивает выполнение таймаутом,
 * возвращает QualityGateResultVo с результатом.
 */
final readonly class QualityGateRunner implements QualityGateRunnerInterface
{
    #[Override]
    public function run(QualityGateVo $gate): QualityGateResultVo
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
