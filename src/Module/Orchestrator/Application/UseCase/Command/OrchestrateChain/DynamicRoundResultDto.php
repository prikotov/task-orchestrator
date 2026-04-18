<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain;

/**
 * DTO результата одного раунда динамической цепочки.
 *
 * Используется для логирования каждого раунда: кто выступал, сколько заняло.
 */
final readonly class DynamicRoundResultDto
{
    public function __construct(
        public int $round,
        public string $role,
        public bool $isFacilitator,
        public string $outputText,
        public int $inputTokens,
        public int $outputTokens,
        public float $cost,
        public float $duration,
        public bool $isError = false,
        public ?string $errorMessage = null,
        public ?string $invocation = null,
        public string $systemPrompt = '',
        public string $userPrompt = '',
    ) {
    }
}
