<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Integration\Service\ChainExecution;

use Override;
use TaskOrchestrator\Common\Component\QueryBus\QueryBusComponentInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\Agent\RunAgent\RunAgentQueryHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\Prompt\GetPromptFilePath\GetPromptFilePathQuery;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\FacilitatorResponseParserInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\RunDynamicLoopAgentServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionWriterInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Shared\DynamicLoopPromptFormatterInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRetryPolicyVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRunRequestVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRunResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopTurnResultVo;

/**
 * Запускает агентов (facilitator/participant) в dynamic-цикле.
 *
 * Использует Application API ChainExecution:
 * - RunAgentDirectCommandHandler — запуск агента
 * - GetPromptFilePathQueryHandler — путь к файлу роли
 *
 * Обращается к foreign Application (Integration → foreign Application — разрешено Deptrac).
 */
final readonly class RunDynamicLoopAgentService implements RunDynamicLoopAgentServiceInterface
{
    public function __construct(
        private RunAgentQueryHandler $agentRunner,
        private DynamicLoopSessionWriterInterface $sessionWriter,
        private FacilitatorResponseParserInterface $responseParser,
        private QueryBusComponentInterface $queryBus,
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
        ?DynamicLoopRetryPolicyVo $retryPolicy = null,
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
            retryPolicy: $retryPolicy,
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
        ?DynamicLoopRetryPolicyVo $retryPolicy = null,
    ): DynamicLoopTurnResultVo {
        /** @var string $resolvedRoleFilePath */
        $resolvedRoleFilePath = $this->queryBus->query(new GetPromptFilePathQuery($role));
        $roleFilePath = $promptFile ?? $resolvedRoleFilePath;
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
            retryPolicy: $retryPolicy,
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
        ?DynamicLoopRetryPolicyVo $retryPolicy = null,
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
            retryPolicy: $retryPolicy,
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
     * Маппит DynamicLoopRunRequestVo → ChainRunRequestVo и запускает через foreign Application.
     *
     * Retry-политика (если задана в config цепочки) пробрасывается в AgentRunner:
     * transient-ошибки (timeout, rate limit, terminated) ретраятся с exponential backoff
     * на уровне runner'а — ниже state dynamic-цикла, поэтому безопасна для journal/rounds.
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

        $chainResult = $this->agentRunner->run(
            $this->truncateRequestContext($chainRequest),
            $this->toExecutionRetryPolicy($request->getRetryPolicy()),
        );

        if ($chainResult->isError()) {
            return DynamicLoopRunResultVo::createError(
                errorMessage: $chainResult->getErrorMessage() ?? 'unknown',
                exitCode: $chainResult->getExitCode(),
                timedOut: $chainResult->isTimedOut(),
            );
        }

        return DynamicLoopRunResultVo::createSuccess(
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

    /**
     * Конвертирует политику retry dynamic-цикла в ExecutionRetryPolicyVo foreign-модуля.
     * null → null (retry выключен, прежнее поведение).
     */
    private function toExecutionRetryPolicy(?DynamicLoopRetryPolicyVo $policy): ?ExecutionRetryPolicyVo
    {
        if ($policy === null || !$policy->isEnabled()) {
            return null;
        }

        return new ExecutionRetryPolicyVo(
            maxRetries: $policy->getMaxRetries(),
            initialDelayMs: $policy->getInitialDelayMs(),
            maxDelayMs: $policy->getMaxDelayMs(),
            multiplier: $policy->getMultiplier(),
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
