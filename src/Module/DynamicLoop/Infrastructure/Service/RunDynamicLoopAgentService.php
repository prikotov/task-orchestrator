<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service;

use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Contract\Agent\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Contract\Prompt\PromptProviderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\FacilitatorResponseParserInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\RunDynamicLoopAgentServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionWriterInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Shared\DynamicLoopPromptFormatterInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRunRequestVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRunResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopTurnResultVo;

/**
 * Запускает агентов (facilitator/participant) в dynamic-цикле.
 *
 * Использует RunAgentServiceInterface из ChainDefinition.Domain\Service\Integration
 * для реального запуска агента через AgentRunner.
 * Маппит DynamicLoop VO → ChainRunRequestVo для совместимости.
 */
final readonly class RunDynamicLoopAgentService implements RunDynamicLoopAgentServiceInterface
{
    public function __construct(
        private RunAgentServiceInterface $agentRunner,
        private DynamicLoopSessionWriterInterface $sessionWriter,
        private FacilitatorResponseParserInterface $responseParser,
        private PromptProviderInterface $promptProvider,
        private DynamicLoopPromptFormatterInterface $formatter,
    ) {
    }

    #[Override]
    public function runFacilitator(
        int $step,
        int $round,
        string $facilitatorRole,
        string $topic,
        string $brainstormSystemPrompt,
        string $facilitatorAppendPrompt,
        string $facilitatorStartPrompt,
        string $facilitatorContinuePrompt,
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

        $systemPromptFile = $this->sessionWriter->writePromptFile(
            $step,
            $round,
            $facilitatorRole,
            $brainstormSystemPrompt,
            '_1_system.md',
        );
        $appendPromptFile = $this->sessionWriter->writePromptFile(
            $step,
            $round,
            $facilitatorRole,
            $facilitatorAppendPrompt,
            '_2_append.md',
        );

        $request = new DynamicLoopRunRequestVo(
            role: $facilitatorRole,
            task: $topic,
            systemPrompt: $systemPromptFile,
            previousContext: $ctx,
            model: null,
            tools: null,
            workingDir: $workingDir,
            timeout: $timeout,
            command: $command,
            runnerArgs: ['--append-system-prompt', $appendPromptFile],
        );

        $start = microtime(true);
        $result = $this->runViaAgentRunner($request);
        $duration = microtime(true) - $start;

        $facilitatorResponse = $this->responseParser->parse($result->getOutputText());
        $turnResult = new DynamicLoopTurnResultVo(
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
        string $role,
        string $topic,
        string $brainstormSystemPrompt,
        string $participantAppendPrompt,
        string $participantUserPrompt,
        ?string $workingDir,
        string $responseFilesList,
        int $timeout,
        array $command = [],
        bool $hasPreviousResponses = true,
        ?string $challenge = null,
        ?string $promptFile = null,
    ): DynamicLoopTurnResultVo {
        $roleFilePath = $promptFile ?? $this->promptProvider->getPromptFilePath($role);
        $appendPromptContent = sprintf($participantAppendPrompt, $roleFilePath);

        $userPrompt = $this->formatter->buildParticipantUserPrompt(
            $participantUserPrompt,
            $topic,
            $responseFilesList,
            $hasPreviousResponses,
            $challenge,
        );

        $systemPromptFile = $this->sessionWriter->writePromptFile(
            $step,
            $round,
            $role,
            $brainstormSystemPrompt,
            '_1_system.md',
        );
        $appendPromptFile = $this->sessionWriter->writePromptFile(
            $step,
            $round,
            $role,
            $appendPromptContent,
            '_2_append.md',
        );

        $request = new DynamicLoopRunRequestVo(
            role: $role,
            task: $topic,
            systemPrompt: $systemPromptFile,
            previousContext: $userPrompt,
            model: null,
            workingDir: $workingDir,
            timeout: $timeout,
            command: $command,
            runnerArgs: ['--append-system-prompt', $appendPromptFile],
        );

        $start = microtime(true);
        $result = $this->runViaAgentRunner($request);
        $duration = microtime(true) - $start;

        return new DynamicLoopTurnResultVo(
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
        string $facilitatorRole,
        string $topic,
        string $brainstormSystemPrompt,
        string $facilitatorAppendPrompt,
        string $facilitatorFinalizePrompt,
        ?string $workingDir,
        string $responseFilesList,
        int $timeout,
        array $command = [],
    ): DynamicLoopTurnResultVo {
        $ctx = $this->formatter->buildFinalizeContext(
            $facilitatorFinalizePrompt,
            $topic,
            $responseFilesList,
        );

        $systemPromptFile = $this->sessionWriter->writePromptFile(
            $step,
            $round,
            $facilitatorRole,
            $brainstormSystemPrompt,
            '_1_system.md',
        );
        $appendPromptFile = $this->sessionWriter->writePromptFile(
            $step,
            $round,
            $facilitatorRole,
            $facilitatorAppendPrompt,
            '_2_append.md',
        );

        $request = new DynamicLoopRunRequestVo(
            role: $facilitatorRole,
            task: $topic,
            systemPrompt: $systemPromptFile,
            previousContext: $ctx,
            model: null,
            tools: null,
            workingDir: $workingDir,
            timeout: $timeout,
            command: $command,
            runnerArgs: ['--append-system-prompt', $appendPromptFile],
        );

        $start = microtime(true);
        $result = $this->runViaAgentRunner($request);
        $duration = microtime(true) - $start;

        return new DynamicLoopTurnResultVo(
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

    /**
     * Маппит DynamicLoopRunRequestVo → ChainRunRequestVo и запускает через RunAgentServiceInterface.
     */
    private function runViaAgentRunner(DynamicLoopRunRequestVo $request): DynamicLoopRunResultVo
    {
        $chainRequest = new ChainRunRequestVo(
            role: $request->getRole(),
            task: $request->getTask(),
            systemPrompt: $request->getSystemPrompt(),
            previousContext: $request->getPreviousContext(),
            model: $request->getModel(),
            tools: $request->getTools(),
            workingDir: $request->getWorkingDir(),
            timeout: $request->getTimeout(),
            maxContextLength: $request->getMaxContextLength(),
            command: $request->getCommand(),
            runnerArgs: $request->getRunnerArgs(),
            runnerName: $request->getRunnerName(),
            noContextFiles: $request->hasNoContextFiles(),
        );

        $chainResult = $this->agentRunner->run($chainRequest->toTruncatedContext());

        if ($chainResult->isError()) {
            return DynamicLoopRunResultVo::createFromError(
                errorMessage: $chainResult->getErrorMessage() ?? 'unknown',
                exitCode: $chainResult->getExitCode(),
                timedOut: $chainResult->isTimedOut(),
            );
        }

        return DynamicLoopRunResultVo::createFromSuccess(
            outputText: $chainResult->getOutputText(),
            inputTokens: $chainResult->getInputTokens(),
            outputTokens: $chainResult->getOutputTokens(),
            cacheReadTokens: $chainResult->getCacheReadTokens(),
            cacheWriteTokens: $chainResult->getCacheWriteTokens(),
            cost: $chainResult->getCost(),
            model: $chainResult->getModel(),
            turns: $chainResult->getTurns(),
        );
    }
}
