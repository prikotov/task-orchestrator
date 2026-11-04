<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\Chain;

use Override;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Agent\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Shared\PromptFormatterInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\ExecuteConditionalStepServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ConditionalStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRoleConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;

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
        ExecutionStepVo $step,
        string $task,
        ?string $workingDir,
        int $timeout,
        ?string $previousContext,
        ?ExecutionRoleConfigVo $roleConfig,
        bool $noContextFiles,
    ): ConditionalStepResultVo {
        return $step->isQualityGate()
            ? $this->runQualityGate($step)
            : $this->runAgentStep($step, $task, $workingDir, $timeout, $previousContext, $roleConfig, $noContextFiles);
    }

    private function runAgentStep(
        ExecutionStepVo $step,
        string $task,
        ?string $workingDir,
        int $timeout,
        ?string $previousContext,
        ?ExecutionRoleConfigVo $roleConfig,
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
            noContextFiles: $noContextFiles || $step->hasNoContextFiles(),
        );

        $start = microtime(true);
        $request = $this->truncateRequestContext($request);
        $result = $this->agentRunner->run($request, $step->getRetryPolicy());
        $duration = microtime(true) - $start;

        return $this->toStepResult($role, $runnerName, $result, $duration);
    }

    private function runQualityGate(ExecutionStepVo $step): ConditionalStepResultVo
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

    private function truncateRequestContext(ChainRunRequestVo $request): ChainRunRequestVo
    {
        $context = $request->getPreviousContext();
        $maxLength = $request->getMaxContextLength();

        if ($context === null || strlen($context) <= $maxLength) {
            return $request;
        }

        return new ChainRunRequestVo(
            role: $request->getRole(),
            task: $request->getTask(),
            systemPrompt: $request->getSystemPrompt(),
            previousContext: substr($context, -$maxLength),
            model: $request->getModel(),
            tools: $request->getTools(),
            workingDir: $request->getWorkingDir(),
            timeout: $request->getTimeout(),
            maxContextLength: $maxLength,
            command: $request->getCommand(),
            runnerArgs: $request->getRunnerArgs(),
            runnerName: $request->getRunnerName(),
            noContextFiles: $request->hasNoContextFiles(),
        );
    }
}
