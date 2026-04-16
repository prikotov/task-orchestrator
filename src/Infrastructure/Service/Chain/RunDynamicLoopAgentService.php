<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Infrastructure\Service\Chain;

use TasK\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TasK\Orchestrator\Domain\Service\Chain\ChainSessionLoggerInterface;
use TasK\Orchestrator\Domain\Service\Chain\FacilitatorResponseParserInterface;
use TasK\Orchestrator\Domain\Service\Chain\PromptFormatterInterface;
use TasK\Orchestrator\Domain\Service\Chain\RunDynamicLoopAgentServiceInterface;
use TasK\Orchestrator\Domain\Service\Prompt\PromptProviderInterface;
use TasK\Orchestrator\Domain\ValueObject\AgentRunRequestVo;
use TasK\Orchestrator\Domain\ValueObject\AgentTurnResultVo;
use Override;

/**
 * Запускает агентов (facilitator/participant) в dynamic-цикле.
 *
 * Инкапсулирует построение AgentRunRequest, запуск агента через runner
 * и парсинг ответа фасилитатора.
 */
final readonly class RunDynamicLoopAgentService implements RunDynamicLoopAgentServiceInterface
{
    public function __construct(
        private ChainSessionLoggerInterface $sessionLogger,
        private FacilitatorResponseParserInterface $responseParser,
        private PromptProviderInterface $promptProvider,
        private PromptFormatterInterface $formatter,
    ) {
    }

    #[Override]
    public function runFacilitator(
        int $step,
        int $round,
        AgentRunnerInterface $runner,
        string $facilitatorRole,
        string $topic,
        string $brainstormSystemPrompt,
        string $facilitatorAppendPrompt,
        string $facilitatorStartPrompt,
        string $facilitatorContinuePrompt,
        ?string $model,
        ?string $workingDir,
        string $facilitatorSummary,
        string $responseFilesList,
        int $timeout,
        array $command = [],
    ): array {
        $ctx = $this->formatter->buildFacilitatorContext(
            $facilitatorStartPrompt,
            $facilitatorContinuePrompt,
            $topic,
            $facilitatorSummary,
            $responseFilesList,
        );

        $systemPromptFile = $this->sessionLogger->writePromptFile(
            $step,
            $round,
            $facilitatorRole,
            $brainstormSystemPrompt,
            '_1_system.md',
        );
        $appendPromptFile = $this->sessionLogger->writePromptFile(
            $step,
            $round,
            $facilitatorRole,
            $facilitatorAppendPrompt,
            '_1b_append.md',
        );
        $command = $this->formatter->resolveSlot($command, '@system-prompt', $systemPromptFile, '--system-prompt');
        $command = $this->formatter->resolveSlot($command, '@append-system-prompt', $appendPromptFile, '--append-system-prompt');

        $request = new AgentRunRequestVo(
            role: $facilitatorRole,
            task: $topic,
            systemPrompt: null,
            previousContext: $ctx,
            model: $model,
            tools: null,
            workingDir: $workingDir,
            timeout: $timeout,
            command: $command,
        );

        $start = microtime(true);
        $result = $runner->run($request->withTruncatedContext());
        $duration = microtime(true) - $start;

        $facilitatorResponse = $this->responseParser->parse($result->getOutputText());
        $turnResult = new AgentTurnResultVo(
            agentResult: $result,
            duration: $duration,
            userPrompt: $ctx,
            systemPrompt: $brainstormSystemPrompt,
            invocation: $this->formatter->buildAgentInvocation(
                $request,
                $this->formatter->buildUserPromptFileName($step, $round, $facilitatorRole),
            ),
        );

        return [$turnResult, $facilitatorResponse];
    }

    #[Override]
    public function runParticipant(
        int $step,
        int $round,
        AgentRunnerInterface $runner,
        string $role,
        string $topic,
        string $brainstormSystemPrompt,
        string $participantAppendPrompt,
        string $participantUserPrompt,
        ?string $model,
        ?string $workingDir,
        string $responseFilesList,
        int $timeout,
        array $command = [],
        bool $hasPreviousResponses = true,
        ?string $challenge = null,
        ?string $promptFile = null,
    ): AgentTurnResultVo {
        $roleFilePath = $promptFile ?? $this->promptProvider->getPromptFilePath($role);
        $appendPromptContent = sprintf($participantAppendPrompt, $roleFilePath);

        $userPrompt = $this->formatter->buildParticipantUserPrompt(
            $participantUserPrompt,
            $topic,
            $responseFilesList,
            $hasPreviousResponses,
            $challenge,
        );

        $systemPromptFile = $this->sessionLogger->writePromptFile(
            $step,
            $round,
            $role,
            $brainstormSystemPrompt,
            '_1_system.md',
        );
        $appendPromptFile = $this->sessionLogger->writePromptFile(
            $step,
            $round,
            $role,
            $appendPromptContent,
            '_1b_append.md',
        );
        $command = $this->formatter->resolveSlot($command, '@system-prompt', $systemPromptFile, '--system-prompt');
        $command = $this->formatter->resolveSlot($command, '@append-system-prompt', $appendPromptFile, '--append-system-prompt');

        $request = new AgentRunRequestVo(
            role: $role,
            task: $topic,
            systemPrompt: null,
            previousContext: $userPrompt,
            model: $model,
            workingDir: $workingDir,
            timeout: $timeout,
            command: $command,
        );

        $start = microtime(true);
        $result = $runner->run($request->withTruncatedContext());
        $duration = microtime(true) - $start;

        return new AgentTurnResultVo(
            agentResult: $result,
            duration: $duration,
            userPrompt: $userPrompt,
            systemPrompt: $brainstormSystemPrompt,
            invocation: $this->formatter->buildAgentInvocation(
                $request,
                $this->formatter->buildUserPromptFileName($step, $round, $role),
            ),
        );
    }

    #[Override]
    public function runFacilitatorFinalize(
        int $step,
        int $round,
        AgentRunnerInterface $runner,
        string $facilitatorRole,
        string $topic,
        string $brainstormSystemPrompt,
        string $facilitatorAppendPrompt,
        string $facilitatorFinalizePrompt,
        ?string $model,
        ?string $workingDir,
        string $responseFilesList,
        int $timeout,
        array $command = [],
    ): AgentTurnResultVo {
        $ctx = $this->formatter->buildFinalizeContext(
            $facilitatorFinalizePrompt,
            $topic,
            $responseFilesList,
        );

        $systemPromptFile = $this->sessionLogger->writePromptFile(
            $step,
            $round,
            $facilitatorRole,
            $brainstormSystemPrompt,
            '_1_system.md',
        );
        $appendPromptFile = $this->sessionLogger->writePromptFile(
            $step,
            $round,
            $facilitatorRole,
            $facilitatorAppendPrompt,
            '_1b_append.md',
        );
        $command = $this->formatter->resolveSlot($command, '@system-prompt', $systemPromptFile, '--system-prompt');
        $command = $this->formatter->resolveSlot($command, '@append-system-prompt', $appendPromptFile, '--append-system-prompt');

        $request = new AgentRunRequestVo(
            role: $facilitatorRole,
            task: $topic,
            systemPrompt: null,
            previousContext: $ctx,
            model: $model,
            tools: null,
            workingDir: $workingDir,
            timeout: $timeout,
            command: $command,
        );

        $start = microtime(true);
        $result = $runner->run($request->withTruncatedContext());
        $duration = microtime(true) - $start;

        return new AgentTurnResultVo(
            agentResult: $result,
            duration: $duration,
            userPrompt: $ctx,
            systemPrompt: $brainstormSystemPrompt,
            invocation: $this->formatter->buildAgentInvocation(
                $request,
                $this->formatter->buildUserPromptFileName($step, $round, $facilitatorRole),
            ),
        );
    }
}
