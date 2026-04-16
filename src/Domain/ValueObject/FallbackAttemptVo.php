<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\ValueObject;

/**
 * Результат попытки fallback runner'а (Domain VO).
 *
 * Заменяет FallbackAttemptResultDto. Содержит данные о результате
 * fallback-вызова, если основной runner вернул ошибку.
 */
final readonly class FallbackAttemptVo
{
    public function __construct(
        public bool $succeeded,
        public string $outputText,
        public int $inputTokens,
        public int $outputTokens,
        public float $cost,
        public bool $isError,
        public ?string $errorMessage,
        public float $extraDuration,
        public ?string $fallbackRunnerName,
    ) {
    }
}
