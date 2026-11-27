<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Результат попытки fallback runner'а (Domain VO).
 *
 * Заменяет FallbackAttemptResultDto. Содержит данные о результате
 * fallback-вызова, если основной runner вернул ошибку.
 */
final readonly class FallbackAttemptVo
{
    public function __construct(
        private bool $succeeded,
        private string $outputText,
        private int $inputTokens,
        private int $outputTokens,
        private float $cost,
        private bool $isError,
        private ?string $errorMessage,
        private float $extraDuration,
        private ?string $fallbackRunnerName,
        private bool $timedOut = false,
    ) {
    }

    public function isSucceeded(): bool
    {
        return $this->succeeded;
    }

    public function getOutputText(): string
    {
        return $this->outputText;
    }

    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    public function getCost(): float
    {
        return $this->cost;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getExtraDuration(): float
    {
        return $this->extraDuration;
    }

    public function getFallbackRunnerName(): ?string
    {
        return $this->fallbackRunnerName;
    }

    public function isTimedOut(): bool
    {
        return $this->timedOut;
    }
}
