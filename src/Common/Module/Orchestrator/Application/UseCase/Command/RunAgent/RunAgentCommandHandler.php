<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\RunAgent;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Prompt\PromptProviderInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;

/**
 * UseCase запуска одного AI-агента.
 *
 * CommandHandler — запускает внешние процессы (I/O).
 */
final readonly class RunAgentCommandHandler
{
    public function __construct(
        private AgentRunnerRegistryServiceInterface $runnerRegistry,
        private PromptProviderInterface $promptProvider,
    ) {
    }

    /**
     * Запускает одного агента с указанной ролью и задачей.
     */
    public function __invoke(RunAgentCommand $command): RunAgentResultDto
    {
        $runnerName = $command->runner ?? 'pi';
        $runner = $this->runnerRegistry->get($runnerName);
        $systemPrompt = $this->promptProvider->getPrompt($command->role);

        $request = new AgentRunRequestVo(
            role: $command->role,
            task: $command->task,
            systemPrompt: $systemPrompt,
            model: $command->model,
            tools: $command->tools,
            workingDir: $command->workingDir,
            timeout: $command->timeout,
        );

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
}
