<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Integration\Adapter;

use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommand;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;

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
        ChainRunRequestVo $vo,
        ?ChainRetryPolicyVo $retryPolicy = null,
    ): RunAgentCommand {
        return new RunAgentCommand(
            role: $vo->getRole(),
            task: $vo->getTask(),
            systemPrompt: $vo->getSystemPrompt(),
            previousContext: $vo->getPreviousContext(),
            model: $vo->getModel(),
            tools: $vo->getTools(),
            workingDir: $vo->getWorkingDir(),
            timeout: $vo->getTimeout(),
            maxContextLength: $vo->getMaxContextLength(),
            command: $vo->getCommand(),
            runnerArgs: $vo->getRunnerArgs(),
            retryMaxRetries: $retryPolicy?->isEnabled() ? $retryPolicy->getMaxRetries() : null,
            retryInitialDelayMs: $retryPolicy?->getInitialDelayMs() ?? 1000,
            retryMaxDelayMs: $retryPolicy?->getMaxDelayMs() ?? 30000,
            retryMultiplier: $retryPolicy?->getMultiplier() ?? 2.0,
        );
    }

    /**
     * Маппит AgentRunner Application RunAgentResultDto → Orchestrator ChainRunResultVo.
     */
    public function mapFromRunAgentResultDto(RunAgentResultDto $dto): ChainRunResultVo
    {
        if ($dto->isError) {
            return ChainRunResultVo::createFromError(
                errorMessage: $dto->errorMessage ?? 'unknown',
                exitCode: $dto->exitCode,
            );
        }

        return ChainRunResultVo::createFromSuccess(
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
