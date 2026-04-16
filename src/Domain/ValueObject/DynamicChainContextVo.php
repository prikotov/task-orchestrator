<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\ValueObject;

/**
 * Контекст dynamic-цепочки: роли, промпты, модель, таймаут.
 *
 * Содержит только primitives — без Application зависимостей.
 *
 * @param array<int, string> $participants
 */
final readonly class DynamicChainContextVo
{
    public function __construct(
        public string $facilitatorRole,
        public array $participants,
        public int $maxRounds,
        public string $topic,
        public string $runnerName,
        public string $brainstormSystemPrompt,
        public string $facilitatorAppendPrompt,
        public string $facilitatorStartPrompt,
        public string $facilitatorContinuePrompt,
        public string $facilitatorFinalizePrompt,
        public string $participantAppendPrompt,
        public string $participantUserPrompt,
        public ?string $model = null,
        public ?string $workingDir = null,
        public int $timeout = 300,
    ) {
    }
}
