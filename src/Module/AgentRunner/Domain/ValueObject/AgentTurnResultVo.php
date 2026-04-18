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
        public AgentResultVo $agentResult,
        public float $duration,
        public string $userPrompt = '',
        public string $systemPrompt = '',
        public ?string $invocation = null,
    ) {
    }
}
