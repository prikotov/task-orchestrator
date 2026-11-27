<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject;

/**
 * Результат выполнения одного хода агента (facilitator/participant).
 *
 * Содержит результат агента, тайминг и метаданные промптов.
 * Immutable VO, используется в Domain-контрактах.
 */
final readonly class AgentTurnResultVo
{
    public function __construct(
        private AgentResultVo $agentResult,
        private float $duration,
        private string $userPrompt = '',
        private string $systemPrompt = '',
        private ?string $invocation = null,
    ) {
    }

    public function getAgentResult(): AgentResultVo
    {
        return $this->agentResult;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getUserPrompt(): string
    {
        return $this->userPrompt;
    }

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function getInvocation(): ?string
    {
        return $this->invocation;
    }
}
