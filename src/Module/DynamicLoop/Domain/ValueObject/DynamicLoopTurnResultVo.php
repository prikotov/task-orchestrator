<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Результат выполнения одного хода агента для dynamic-цикла.
 *
 * Копия ChainDefinition\ChainTurnResultVo, без зависимости от ChainDefinition.Domain.
 */
final readonly class DynamicLoopTurnResultVo
{
    public function __construct(
        private DynamicLoopRunResultVo $agentResult,
        private float $duration,
        private string $userPrompt = '',
        private string $systemPrompt = '',
        private ?string $invocation = null,
    ) {
    }

    public function getAgentResult(): DynamicLoopRunResultVo
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
