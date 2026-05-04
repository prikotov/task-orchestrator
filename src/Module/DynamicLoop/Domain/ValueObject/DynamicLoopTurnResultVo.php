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
        public DynamicLoopRunResultVo $agentResult,
        public float $duration,
        public string $userPrompt = '',
        public string $systemPrompt = '',
        public ?string $invocation = null,
    ) {
    }
}
