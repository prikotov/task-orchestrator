<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\ValueObject;

/**
 * Результат одного раунда dynamic-цикла: метрики, роль, статус.
 *
 * Domain-аналог Application DTO DynamicRoundResultDto.
 * Содержит только primitives — без Application зависимостей.
 */
final readonly class DynamicRoundResultVo
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
