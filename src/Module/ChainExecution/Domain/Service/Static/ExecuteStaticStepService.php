<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Agent\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\FormatPromptServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionFallbackConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRoleConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\FallbackAttemptVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;

/**
 * Выполнение отдельного шага static-цепочки: agent-step, quality-gate, fallback.
 *
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @todo PHPMD bug: multi-file analysis inflates LOC counts. Recheck after PHPMD upgrade.
 */
final readonly class ExecuteStaticStepService
{
    private const string QUALITY_GATE_RUNNER_NAME = 'shell';
    private const string TOOL_RUNNER_NAME = 'shell';

    public function __construct(
        private RunAgentServiceInterface $agentRunner,
        private ResolveChainRunnerServiceInterface $runnerHelper,
        private FormatPromptServiceInterface $formatter,
        private ?QualityGateRunnerInterface $qualityGateRunner = null,
        private ?ToolStepRunnerInterface $toolStepRunner = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function runAgentStep(
        ExecutionStepVo $step,
        string $task,
        ?string $workingDir,
        int $timeout,
        ?string $previousContext,
        ?int $iterationNumber,
        ?ExecutionRoleConfigVo $roleConfig,
        bool $noContextFiles = false,
    ): StaticStepResultVo {
        $role = $step->getRole() ?? '';
        $context = $previousContext !== null
            ? $this->formatter->buildStaticContext(
                $role,
                $previousContext,
                $task,
            )
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

        $fallbackRunnerUsed = null;
        $fallbackConfig = $roleConfig?->getFallback();
        $timedOut = $result->isTimedOut();
        if ($result->isError() && $fallbackConfig !== null) {
            $fallbackResult = $this->applyFallback(
                $fallbackConfig,
                $role,
                $runnerName,
                $step,
                $request,
                $roleConfig?->getPromptFile(),
            );
            $duration += $fallbackResult->extraDuration;
            $fallbackRunnerUsed = $fallbackResult->fallbackRunnerName;
            if ($fallbackResult->fallbackRunnerName !== null) {
                $result = $fallbackResult->isError
                    ? ChainRunResultVo::createError(
                        $fallbackResult->errorMessage ?? 'unknown',
                        timedOut: $fallbackResult->timedOut,
                    )
                    : ChainRunResultVo::createSuccess(
                        $fallbackResult->outputText,
                        $fallbackResult->inputTokens,
                        $fallbackResult->outputTokens,
                        cost: $fallbackResult->cost,
                    );
                if (!$fallbackResult->isError) {
                    $timedOut = false;
                } elseif ($fallbackResult->timedOut) {
                    $timedOut = true;
                }
            }
        }

        return new StaticStepResultVo(
            role: $role,
            runner: $runnerName,
            outputText: $result->getOutputText(),
            inputTokens: $result->getInputTokens(),
            outputTokens: $result->getOutputTokens(),
            cost: $result->getCost(),
            duration: $duration,
            isError: $result->isError(),
            errorMessage: $result->getErrorMessage(),
            fallbackRunnerUsed: $fallbackRunnerUsed,
            iterationNumber: $iterationNumber,
            timedOut: $timedOut,
        );
    }

    public function runQualityGate(
        ExecutionStepVo $step,
    ): StaticStepResultVo {
        if ($this->qualityGateRunner === null) {
            return new StaticStepResultVo(
                role: 'quality_gate',
                runner: self::QUALITY_GATE_RUNNER_NAME,
                outputText: '',
                inputTokens: 0,
                outputTokens: 0,
                cost: 0.0,
                duration: 0.0,
                isError: false,
                label: $step->getLabel(),
                passed: true,
            );
        }

        $result = $this->qualityGateRunner->run($step->toQualityGateVo());
        $duration = $result->durationMs / 1000.0;

        if (!$result->passed) {
            $this->logger?->warning(
                sprintf(
                    '[StaticChainExecutor] Quality gate "%s" failed (exit code %d): %s',
                    $result->label,
                    $result->exitCode,
                    $result->output,
                ),
            );
        }

        return new StaticStepResultVo(
            role: 'quality_gate',
            runner: self::QUALITY_GATE_RUNNER_NAME,
            outputText: $result->output,
            inputTokens: 0,
            outputTokens: 0,
            cost: 0.0,
            duration: $duration,
            isError: false,
            label: $result->label,
            passed: $result->passed,
            exitCode: $result->exitCode,
        );
    }

    /**
     * Выполняет tool-шаг: shell-команда с передачей stdout в context.
     *
     * Если exit code ≠ 0 — шаг помечается как ошибка (error policy: fail),
     * что останавливает цепочку.
     *
     * @return StaticStepResultVo результат с outputText = stdout
     */
    public function runToolStep(
        ExecutionStepVo $step,
    ): StaticStepResultVo {
        if ($this->toolStepRunner === null) {
            // Без runner'а шаг считается успешным (no-op)
            return new StaticStepResultVo(
                role: 'tool',
                runner: self::TOOL_RUNNER_NAME,
                outputText: '',
                inputTokens: 0,
                outputTokens: 0,
                cost: 0.0,
                duration: 0.0,
                isError: false,
                label: $step->getLabel(),
                exitCode: 0,
            );
        }

        $result = $this->toolStepRunner->run($step->toToolStepVo());
        $duration = $result->durationMs / 1000.0;

        if (!$result->success) {
            $this->logger?->warning(
                sprintf(
                    '[StaticChainExecutor] Tool step "%s" failed (exit code %d): %s',
                    $step->getLabel(),
                    $result->exitCode,
                    $result->stdout,
                ),
            );
        }

        return new StaticStepResultVo(
            role: 'tool',
            runner: self::TOOL_RUNNER_NAME,
            outputText: $result->stdout,
            inputTokens: 0,
            outputTokens: 0,
            cost: 0.0,
            duration: $duration,
            isError: !$result->success,
            errorMessage: !$result->success
                ? sprintf('Tool "%s" failed with exit code %d', $step->getLabel(), $result->exitCode)
                : null,
            label: $step->getLabel(),
            exitCode: $result->exitCode,
            outputKey: $step->getOutputKey(),
        );
    }

    public function createAgentResultFromStep(
        StaticStepResultVo $stepResult,
    ): ChainRunResultVo {
        if ($stepResult->isError) {
            return ChainRunResultVo::createError(
                $stepResult->errorMessage ?? 'unknown',
                timedOut: $stepResult->timedOut,
            );
        }

        return ChainRunResultVo::createSuccess(
            $stepResult->outputText,
            $stepResult->inputTokens,
            $stepResult->outputTokens,
            cost: $stepResult->cost,
        );
    }

    private function applyFallback(
        ExecutionFallbackConfigVo $fallbackConfig,
        string $role,
        string $runnerName,
        ExecutionStepVo $step,
        ChainRunRequestVo $request,
        ?string $promptFile,
    ): FallbackAttemptVo {
        $fallbackStart = microtime(true);
        $fallbackResult = $this->runnerHelper->tryFallbackRunner(
            $fallbackConfig,
            $role,
            $runnerName,
            $step->getRetryPolicy(),
            $request,
            $promptFile,
        );
        $extraDuration = microtime(true) - $fallbackStart;

        return new FallbackAttemptVo(
            succeeded: $fallbackResult !== null && !$fallbackResult->isError(),
            outputText: $fallbackResult?->getOutputText() ?? '',
            inputTokens: $fallbackResult?->getInputTokens() ?? 0,
            outputTokens: $fallbackResult?->getOutputTokens() ?? 0,
            cost: $fallbackResult?->getCost() ?? 0.0,
            isError: $fallbackResult?->isError() ?? true,
            errorMessage: $fallbackResult?->getErrorMessage(),
            extraDuration: $extraDuration,
            fallbackRunnerName: $fallbackResult !== null
                ? $fallbackConfig->getRunnerName()
                : null,
            timedOut: $fallbackResult?->isTimedOut() ?? false,
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
