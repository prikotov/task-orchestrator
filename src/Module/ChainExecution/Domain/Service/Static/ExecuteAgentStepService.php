<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Agent\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\FormatPromptServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ResolveChainRunnerServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionFallbackConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\FallbackAttemptVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StepContextVo;

/**
 * Сервис выполнения agent-шага static-цепочки.
 *
 * Выполняет AI-агента, обрабатывает fallback при ошибке,
 * усекает контекст при превышении лимита.
 *
 * @todo 2026-05-21: PHPMD bug: multi-file analysis reports 80 LOC for run(), single-file = 61. Recheck after PHPMD upgrade.
 */
final readonly class ExecuteAgentStepService implements ExecuteStepServiceInterface
{
    public function __construct(
        private RunAgentServiceInterface $agentRunner,
        private ResolveChainRunnerServiceInterface $runnerHelper,
        private FormatPromptServiceInterface $formatter,
    ) {
    }

    #[Override]
    public function supports(ChainStepTypeEnum $type): bool
    {
        return $type === ChainStepTypeEnum::agent;
    }

    #[Override]
    public function run(ExecutionStepVo $step, StepContextVo $context): StaticStepResultVo
    {
        $role = $step->getRole() ?? '';
        $formattedContext = $context->previousContext !== null
            ? $this->formatter->buildStaticContext(
                $role,
                $context->previousContext,
                $context->task,
            )
            : null;

        $runnerName = $step->getRunner();
        $request = new ChainRunRequestVo(
            role: $role,
            task: $context->task,
            systemPrompt: null,
            previousContext: $formattedContext,
            model: $step->getModel(),
            tools: $step->getTools(),
            workingDir: $context->workingDir,
            timeout: $context->roleConfig?->getTimeout() ?? $context->timeout,
            command: $context->roleConfig?->getCommand() ?? [],
            runnerName: $runnerName,
            noContextFiles: $context->noContextFiles || $step->hasNoContextFiles(),
        );

        $start = microtime(true);
        $request = $this->truncateRequestContext($request);
        $result = $this->agentRunner->run($request, $step->getRetryPolicy());
        $duration = microtime(true) - $start;
        $timedOut = $result->isTimedOut();

        $fallbackConfig = $context->roleConfig?->getFallback();
        $fallbackRunnerUsed = null;
        if ($result->isError() && $fallbackConfig !== null) {
            [$result, $duration, $fallbackRunnerUsed, $timedOut] = $this->tryFallback(
                $fallbackConfig,
                $role,
                $runnerName,
                $step,
                $request,
                $duration,
                $context->roleConfig?->getPromptFile(), // @phpstan-ignore nullsafe.neverNull
                $result,
                $timedOut,
            );
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
            iterationNumber: $context->iterationNumber,
            timedOut: $timedOut,
        );
    }

    /**
     * @return array{ChainRunResultVo, float, ?string, bool}
     */
    private function tryFallback(
        ExecutionFallbackConfigVo $fallbackConfig,
        string $role,
        string $runnerName,
        ExecutionStepVo $step,
        ChainRunRequestVo $request,
        float $duration,
        ?string $promptFile,
        ChainRunResultVo $originalResult,
        bool $originalTimedOut,
    ): array {
        $fallbackResult = $this->applyFallback(
            $fallbackConfig,
            $role,
            $runnerName,
            $step,
            $request,
            $promptFile,
        );
        $duration += $fallbackResult->extraDuration;
        $fallbackRunnerName = $fallbackResult->fallbackRunnerName;

        if ($fallbackRunnerName !== null) {
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
            $timedOut = !$fallbackResult->isError ? false : $fallbackResult->timedOut;

            return [$result, $duration, $fallbackRunnerName, $timedOut];
        }

        return [$originalResult, $duration, null, $originalTimedOut];
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
        $contextStr = $request->getPreviousContext();
        $maxLength = $request->getMaxContextLength();

        if ($contextStr === null || strlen($contextStr) <= $maxLength) {
            return $request;
        }

        return new ChainRunRequestVo(
            role: $request->getRole(),
            task: $request->getTask(),
            systemPrompt: $request->getSystemPrompt(),
            previousContext: substr($contextStr, -$maxLength),
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
