<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Adapter;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentTurnResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\RetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainTurnResultVo;

/**
 * Маппер VO между Orchestrator Domain и AgentRunner Domain.
 *
 * Stateless-маппер: преобразует Chain*-VO ↔ Agent*-VO на границе модулей.
 * Методы отражают направление: mapTo*() — Orchestrator → AgentRunner,
 * mapFrom*() — AgentRunner → Orchestrator.
 */
final readonly class AgentVoMapper
{
    /**
     * Маппит Orchestrator ChainRunRequestVo → AgentRunner AgentRunRequestVo.
     */
    public function mapToAgentRequest(ChainRunRequestVo $vo): AgentRunRequestVo
    {
        return new AgentRunRequestVo(
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
        );
    }

    /**
     * Маппит AgentRunner AgentResultVo → Orchestrator ChainRunResultVo.
     */
    public function mapFromAgentResult(AgentResultVo $vo): ChainRunResultVo
    {
        if ($vo->isError()) {
            return ChainRunResultVo::createFromError(
                errorMessage: $vo->getErrorMessage() ?? 'unknown',
                exitCode: $vo->getExitCode(),
            );
        }

        return ChainRunResultVo::createFromSuccess(
            outputText: $vo->getOutputText(),
            inputTokens: $vo->getInputTokens(),
            outputTokens: $vo->getOutputTokens(),
            cacheReadTokens: $vo->getCacheReadTokens(),
            cacheWriteTokens: $vo->getCacheWriteTokens(),
            cost: $vo->getCost(),
            model: $vo->getModel(),
            turns: $vo->getTurns(),
        );
    }

    /**
     * Маппит AgentRunner AgentTurnResultVo → Orchestrator ChainTurnResultVo.
     */
    public function mapFromAgentTurnResult(AgentTurnResultVo $vo): ChainTurnResultVo
    {
        return new ChainTurnResultVo(
            agentResult: $this->mapFromAgentResult($vo->agentResult),
            duration: $vo->duration,
            userPrompt: $vo->userPrompt,
            systemPrompt: $vo->systemPrompt,
            invocation: $vo->invocation,
        );
    }

    /**
     * Маппит Orchestrator ChainRetryPolicyVo → AgentRunner RetryPolicyVo.
     *
     * Возвращает null, если политика не задана или отключена.
     */
    public function mapToAgentRetryPolicy(?ChainRetryPolicyVo $vo): ?RetryPolicyVo
    {
        if ($vo === null || !$vo->isEnabled()) {
            return null;
        }

        return new RetryPolicyVo(
            maxRetries: $vo->getMaxRetries(),
            initialDelayMs: $vo->getInitialDelayMs(),
            maxDelayMs: $vo->getMaxDelayMs(),
            multiplier: $vo->getMultiplier(),
        );
    }
}
