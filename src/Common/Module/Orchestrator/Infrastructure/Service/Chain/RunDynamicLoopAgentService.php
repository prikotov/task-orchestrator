<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\FacilitatorResponseParserInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\PromptFormatterInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\RunDynamicLoopAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Prompt\PromptProviderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainTurnResultVo;
use Override;

/**
 * Запускает агентов (facilitator/participant) в dynamic-цикле.
 *
 * Инкапсулирует построение AgentRunRequest, запуск агента через runner
 * и парсинг ответа фасилитатора.
 *
 * Prompt-файлы (system + append) передаются через VO-поля:
 * - systemPrompt → --system-prompt <path> (добавляется PiAgentRunner)
 * - runnerArgs → --append-system-prompt <path> (добавляется PiAgentRunner)
 * - command → полная CLI-команда из role config (пусто = runner default)
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
        AgentRunnerPortInterface $runner,
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

        $request = new ChainRunRequestVo(
            role: $facilitatorRole,
            task: $topic,
            systemPrompt: $systemPromptFile,
            previousContext: $ctx,
            model: $model,
            tools: null,
            workingDir: $workingDir,
            timeout: $timeout,
            command: $command,
            runnerArgs: ['--append-system-prompt', $appendPromptFile],
        );

        $start = microtime(true);
        $result = $runner->run($request->withTruncatedContext());
        $duration = microtime(true) - $start;

        $facilitatorResponse = $this->responseParser->parse($result->getOutputText());
        $turnResult = new ChainTurnResultVo(
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
        AgentRunnerPortInterface $runner,
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
    ): ChainTurnResultVo {
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

        $request = new ChainRunRequestVo(
            role: $role,
            task: $topic,
            systemPrompt: $systemPromptFile,
            previousContext: $userPrompt,
            model: $model,
            workingDir: $workingDir,
            timeout: $timeout,
            command: $command,
            runnerArgs: ['--append-system-prompt', $appendPromptFile],
        );

        $start = microtime(true);
        $result = $runner->run($request->withTruncatedContext());
        $duration = microtime(true) - $start;

        return new ChainTurnResultVo(
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
        AgentRunnerPortInterface $runner,
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
    ): ChainTurnResultVo {
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

        $request = new ChainRunRequestVo(
            role: $facilitatorRole,
            task: $topic,
            systemPrompt: $systemPromptFile,
            previousContext: $ctx,
            model: $model,
            tools: null,
            workingDir: $workingDir,
            timeout: $timeout,
            command: $command,
            runnerArgs: ['--append-system-prompt', $appendPromptFile],
        );

        $start = microtime(true);
        $result = $runner->run($request->withTruncatedContext());
        $duration = microtime(true) - $start;

        return new ChainTurnResultVo(
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
