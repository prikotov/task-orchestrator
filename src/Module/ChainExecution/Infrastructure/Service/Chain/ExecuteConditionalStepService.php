<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\Chain;

use Override;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Shared\PromptFormatterInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\ExecuteConditionalStepServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ConditionalStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\RoleConfigVo;

/**
 * Infrastructure Service: выполнение одного шага conditional-цепочки.
 *
 * Agent-шаг: форматирует контекст, создаёт ChainRunRequestVo, делегирует в RunAgentServiceInterface.
 * Quality gate: выполняет shell-команду через Symfony Process.
 */
final readonly class ExecuteConditionalStepService implements ExecuteConditionalStepServiceInterface
{
    private const string QUALITY_GATE_RUNNER_NAME = 'shell';

    public function __construct(
        private RunAgentServiceInterface $agentRunner,
        private PromptFormatterInterface $promptFormatter,
    ) {
    }

    #[Override]
    public function executeStep(
        ChainStepVo $step,
        string $task,
        ?string $workingDir,
        int $timeout,
        ?string $previousContext,
        ?RoleConfigVo $roleConfig,
        bool $noContextFiles,
    ): ConditionalStepResultVo {
        return $step->isQualityGate()
            ? $this->runQualityGate($step)
            : $this->runAgentStep($step, $task, $workingDir, $timeout, $previousContext, $roleConfig, $noContextFiles);
    }

    private function runAgentStep(
        ChainStepVo $step,
        string $task,
        ?string $workingDir,
        int $timeout,
        ?string $previousContext,
        ?RoleConfigVo $roleConfig,
        bool $noContextFiles,
    ): ConditionalStepResultVo {
        $role = $step->getRole() ?? '';
        $context = $previousContext !== null
            ? $this->promptFormatter->buildStaticContext($role, $previousContext, $task)
            : null;

        $runnerName = $step->getRunner();
        $request = new ChainRunRequestVo(
            role: $role,
            task: $task,
            systemPrompt: null,
            previousContext: $context,
            model: $step->getModel(),
            tools: $step->getTools(),
            workingDir: $workingDir,
            timeout: $roleConfig?->getTimeout() ?? $timeout,
            command: $roleConfig?->getCommand() ?? [],
            runnerName: $runnerName,
            noContextFiles: $noContextFiles || $step->getNoContextFiles(),
        );

        $start = microtime(true);
        $result = $this->agentRunner->run($request->withTruncatedContext(), $step->getRetryPolicy());
        $duration = microtime(true) - $start;

        return $this->toStepResult($role, $runnerName, $result, $duration);
    }

    private function runQualityGate(ChainStepVo $step): ConditionalStepResultVo
    {
        $command = $step->getCommand();
        $label = $step->getLabel();
        $timeoutSeconds = $step->getTimeoutSeconds();

        $start = microtime(true);
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeoutSeconds);
        $process->run();
        $duration = microtime(true) - $start;

        $exitCode = $process->getExitCode() ?? 1;
        $passed = $exitCode === 0;
        $output = $process->getOutput() . $process->getErrorOutput();
        $timedOut = !$process->isSuccessful() && $process->hasBeenSignaled();

        return new ConditionalStepResultVo(
            role: 'quality_gate',
            runner: self::QUALITY_GATE_RUNNER_NAME,
            outputText: $output,
            inputTokens: 0,
            outputTokens: 0,
            cost: 0.0,
            duration: $duration,
            isError: false,
            errorMessage: null,
            passed: $passed,
            exitCode: $exitCode,
            label: $label,
            timedOut: $timedOut,
        );
    }

    private function toStepResult(
        string $role,
        string $runner,
        ChainRunResultVo $result,
        float $duration,
    ): ConditionalStepResultVo {
        return new ConditionalStepResultVo(
            role: $role,
            runner: $runner,
            outputText: $result->getOutputText(),
            inputTokens: $result->getInputTokens(),
            outputTokens: $result->getOutputTokens(),
            cost: $result->getCost(),
            duration: $duration,
            isError: $result->isError(),
            errorMessage: $result->getErrorMessage(),
            timedOut: $result->isTimedOut(),
        );
    }
}
