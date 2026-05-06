<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\RunAgent;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\Contract\Agent\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Contract\Prompt\PromptProviderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;

/**
 * UseCase запуска одного AI-агента.
 *
 * CommandHandler — запускает внешние процессы (I/O).
 */
final readonly class RunAgentCommandHandler
{
    public function __construct(
        private RunAgentServiceInterface $agentRunner,
        private PromptProviderInterface $promptProvider,
    ) {
    }

    /**
     * Запускает одного агента с указанной ролью и задачей.
     */
    public function __invoke(RunAgentCommand $command): RunAgentResultDto
    {
        $systemPrompt = $this->promptProvider->getPrompt($command->role);

        $request = new ChainRunRequestVo(
            role: $command->role,
            task: $command->task,
            systemPrompt: $systemPrompt,
            model: $command->model,
            tools: $command->tools,
            workingDir: $command->workingDir,
            timeout: $command->timeout,
            runnerName: $command->runner ?? 'pi',
            noContextFiles: $command->noContextFiles,
        );

        $request = $this->truncateRequestContext($request);
        $result = $this->agentRunner->run($request);

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
