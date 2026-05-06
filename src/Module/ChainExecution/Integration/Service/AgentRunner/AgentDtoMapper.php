<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\AgentRunner;

use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommand;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;

/**
 * Маппер между Orchestrator Domain VO и AgentRunner Application DTO.
 *
 * Stateless-маппер: преобразует Chain*-VO ↔ RunAgent*Dto на границе модулей.
 * Методы отражают направление: mapTo*() — Orchestrator → AgentRunner Application,
 * mapFrom*() — AgentRunner Application → Orchestrator.
 *
 * ACL (Anti-Corruption Layer): обеспечивает изоляцию модулей.
 */
final readonly class AgentDtoMapper
{
    /**
     * Маппит Orchestrator ChainRunRequestVo → AgentRunner Application RunAgentCommand.
     */
    public function mapToRunAgentCommand(
        ChainRunRequestVo $valueObject,
        ?ExecutionRetryPolicyVo $retryPolicy = null,
    ): RunAgentCommand {
        $runnerName = $valueObject->getRunnerName();
        if (($runnerName === null || $runnerName === '') && $valueObject->getCommand() !== []) {
            $runnerName = $valueObject->getCommand()[0];
        }
        $runnerName = $runnerName ?? '';

        return new RunAgentCommand(
            runnerName: $runnerName,
            role: $valueObject->getRole(),
            task: $valueObject->getTask(),
            systemPrompt: $valueObject->getSystemPrompt(),
            previousContext: $valueObject->getPreviousContext(),
            model: $valueObject->getModel(),
            tools: $valueObject->getTools(),
            workingDir: $valueObject->getWorkingDir(),
            timeout: $valueObject->getTimeout(),
            maxContextLength: $valueObject->getMaxContextLength(),
            command: $valueObject->getCommand(),
            runnerArgs: $valueObject->getRunnerArgs(),
            retryMaxRetries: $retryPolicy?->isEnabled() ? $retryPolicy->getMaxRetries() : null,
            retryInitialDelayMs: $retryPolicy?->getInitialDelayMs() ?? 1000,
            retryMaxDelayMs: $retryPolicy?->getMaxDelayMs() ?? 30000,
            retryMultiplier: $retryPolicy?->getMultiplier() ?? 2.0,
            noContextFiles: $valueObject->hasNoContextFiles(),
        );
    }

    /**
     * Маппит AgentRunner Application RunAgentResultDto → Orchestrator ChainRunResultVo.
     */
    public function mapFromRunAgentResultDto(RunAgentResultDto $dto): ChainRunResultVo
    {
        if ($dto->isError) {
            return ChainRunResultVo::createError(
                errorMessage: $dto->errorMessage ?? 'unknown',
                exitCode: $dto->exitCode,
                timedOut: $dto->timedOut,
            );
        }

        return ChainRunResultVo::createSuccess(
            outputText: $dto->outputText,
            inputTokens: $dto->inputTokens,
            outputTokens: $dto->outputTokens,
            cacheReadTokens: $dto->cacheReadTokens,
            cacheWriteTokens: $dto->cacheWriteTokens,
            cost: $dto->cost,
            model: $dto->model,
            turns: $dto->turns,
        );
    }
}
