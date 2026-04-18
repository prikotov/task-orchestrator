<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * Результат выполнения одного хода агента через Port.
 *
 * Orchestrator Domain VO — дубликат AgentRunner\AgentTurnResultVo.
 * Маппинг из AgentTurnResultVo выполняется в Infrastructure Adapter.
 *
 * Содержит результат агента, тайминг и метаданные промптов.
 * Immutable VO, используется в Domain-контрактах.
 */
final readonly class ChainTurnResultVo
{
    public function __construct(
        public ChainRunResultVo $agentResult,
        public float $duration,
        public string $userPrompt = '',
        public string $systemPrompt = '',
        public ?string $invocation = null,
    ) {
    }
}
