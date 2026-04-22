<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\RetryPolicyVo;

/**
 * UseCase запуска AI-агента через Application-слой.
 *
 * Принимает DTO (примитивы), маппит в Domain VO,
 * вызывает Domain-сервисы, возвращает DTO.
 * Retry инкапсулирован: если retryMaxRetries задан, оборачивает runner.
 */
final readonly class RunAgentCommandHandler
{
    public function __construct(
        private AgentRunnerRegistryServiceInterface $registry,
        private RetryableRunnerFactoryInterface $retryFactory,
    ) {
    }

    /**
     * Запускает агента с заданными параметрами.
     */
    public function __invoke(RunAgentCommand $command): RunAgentResultDto
    {
        $runner = $this->resolveRunner($command->runnerName);
        $runner = $this->applyRetry($runner, $command);

        $request = $this->buildRequest($command);
        $result = $runner->run($request->withTruncatedContext());

        return new RunAgentResultDto(
            outputText: $result->getOutputText(),
            inputTokens: $result->getInputTokens(),
            outputTokens: $result->getOutputTokens(),
            cacheReadTokens: $result->getCacheReadTokens(),
            cacheWriteTokens: $result->getCacheWriteTokens(),
            cost: $result->getCost(),
            exitCode: $result->getExitCode(),
            model: $result->getModel(),
            turns: $result->getTurns(),
            isError: $result->isError(),
            errorMessage: $result->getErrorMessage(),
        );
    }

    private function resolveRunner(string $runnerName): AgentRunnerInterface
    {
        return $runnerName !== ''
            ? $this->registry->get($runnerName)
            : $this->registry->getDefault();
    }

    private function applyRetry(AgentRunnerInterface $runner, RunAgentCommand $command): AgentRunnerInterface
    {
        if ($command->retryMaxRetries === null || $command->retryMaxRetries <= 0) {
            return $runner;
        }

        $retryVo = new RetryPolicyVo(
            maxRetries: $command->retryMaxRetries,
            initialDelayMs: $command->retryInitialDelayMs,
            maxDelayMs: $command->retryMaxDelayMs,
            multiplier: $command->retryMultiplier,
        );

        return $this->retryFactory->createRetryableRunner($runner, $retryVo);
    }

    private function buildRequest(RunAgentCommand $command): AgentRunRequestVo
    {
        return new AgentRunRequestVo(
            role: $command->role,
            task: $command->task,
            systemPrompt: $command->systemPrompt,
            previousContext: $command->previousContext,
            model: $command->model,
            tools: $command->tools,
            workingDir: $command->workingDir,
            timeout: $command->timeout,
            maxContextLength: $command->maxContextLength,
            command: $command->command,
            runnerArgs: $command->runnerArgs,
            noContextFiles: $command->noContextFiles,
        );
    }
}
