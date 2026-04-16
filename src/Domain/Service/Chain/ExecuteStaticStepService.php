<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TasK\Orchestrator\Domain\ValueObject\AgentResultVo;
use TasK\Orchestrator\Domain\ValueObject\AgentRunRequestVo;
use TasK\Orchestrator\Domain\ValueObject\ChainStepVo;
use TasK\Orchestrator\Domain\ValueObject\FallbackAttemptVo;
use TasK\Orchestrator\Domain\ValueObject\RoleConfigVo;
use TasK\Orchestrator\Domain\ValueObject\StaticStepResultVo;
use Psr\Log\LoggerInterface;

use function sprintf;

/**
 * Выполнение отдельного шага static-цепочки: agent-step, quality-gate, fallback.
 *
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @todo PHPMD bug: multi-file analysis inflates LOC counts. Recheck after PHPMD upgrade.
 */
final readonly class ExecuteStaticStepService
{
    private const string QUALITY_GATE_RUNNER_NAME = 'shell';

    public function __construct(
        private ResolveChainRunnerServiceInterface $runnerHelper,
        private PromptFormatterInterface $formatter,
        private ?QualityGateRunnerInterface $qualityGateRunner = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function runAgentStep(
        ChainStepVo $step,
        AgentRunnerInterface $runner,
        string $runnerName,
        string $task,
        ?string $model,
        ?string $workingDir,
        int $timeout,
        ?string $previousContext,
        ?int $iterationNumber,
        ?RoleConfigVo $roleConfig,
    ): StaticStepResultVo {
        $role = $step->getRole() ?? '';
        $context = $previousContext !== null
            ? $this->formatter->buildStaticContext(
                $role,
                $previousContext,
                $task,
            )
            : null;

        $request = new AgentRunRequestVo(
            role: $role,
            task: $task,
            systemPrompt: null,
            previousContext: $context,
            model: $model ?? $step->getModel(),
            tools: $step->getTools(),
            workingDir: $workingDir,
            timeout: $roleConfig?->getTimeout() ?? $timeout,
            command: $roleConfig?->getCommand() ?? [],
        );

        $effectiveRunner = $this->runnerHelper->createRunnerWithRetry(
            $runner,
            $step->getRetryPolicy(),
        );

        $start = microtime(true);
        $result = $effectiveRunner->run($request->withTruncatedContext());
        $duration = microtime(true) - $start;

        $fallbackRunnerUsed = null;
        $fallbackConfig = $roleConfig?->getFallback();
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
                    ? AgentResultVo::createFromError($fallbackResult->errorMessage ?? 'unknown')
                    : AgentResultVo::createFromSuccess(
                        $fallbackResult->outputText,
                        $fallbackResult->inputTokens,
                        $fallbackResult->outputTokens,
                        cost: $fallbackResult->cost,
                    );
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
        );
    }

    public function runQualityGate(
        ChainStepVo $step,
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

    public function createAgentResultFromStep(
        StaticStepResultVo $stepResult,
    ): AgentResultVo {
        if ($stepResult->isError) {
            return AgentResultVo::createFromError(
                $stepResult->errorMessage ?? 'unknown',
            );
        }

        return AgentResultVo::createFromSuccess(
            $stepResult->outputText,
            $stepResult->inputTokens,
            $stepResult->outputTokens,
            cost: $stepResult->cost,
        );
    }

    private function applyFallback(
        \TasK\Orchestrator\Domain\ValueObject\FallbackConfigVo $fallbackConfig,
        string $role,
        string $runnerName,
        ChainStepVo $step,
        AgentRunRequestVo $request,
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
        );
    }
}
