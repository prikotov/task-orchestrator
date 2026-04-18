<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * Value Object результата выполнения AI-агента через Port.
 *
 * Orchestrator Domain VO — дубликат AgentRunner\AgentResultVo.
 * Маппинг из AgentResultVo выполняется в Infrastructure Adapter.
 *
 * Immutable, содержит выходные данные агента и метрики использования.
 */
final readonly class ChainRunResultVo
{
    private function __construct(
        private string $outputText,
        private int $inputTokens,
        private int $outputTokens,
        private int $cacheReadTokens,
        private int $cacheWriteTokens,
        private float $cost,
        private int $exitCode,
        private ?string $model,
        private int $turns,
        private bool $isError,
        private ?string $errorMessage,
    ) {
    }

    /**
     * Создаёт успешный результат.
     */
    public static function createFromSuccess(
        string $outputText,
        int $inputTokens = 0,
        int $outputTokens = 0,
        int $cacheReadTokens = 0,
        int $cacheWriteTokens = 0,
        float $cost = 0.0,
        ?string $model = null,
        int $turns = 0,
    ): self {
        return new self(
            outputText: $outputText,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cacheReadTokens: $cacheReadTokens,
            cacheWriteTokens: $cacheWriteTokens,
            cost: $cost,
            exitCode: 0,
            model: $model,
            turns: $turns,
            isError: false,
            errorMessage: null,
        );
    }

    /**
     * Создаёт результат с ошибкой.
     */
    public static function createFromError(
        string $errorMessage,
        int $exitCode = 1,
    ): self {
        return new self(
            outputText: '',
            inputTokens: 0,
            outputTokens: 0,
            cacheReadTokens: 0,
            cacheWriteTokens: 0,
            cost: 0.0,
            exitCode: $exitCode,
            model: null,
            turns: 0,
            isError: true,
            errorMessage: $errorMessage,
        );
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

    public function getCacheReadTokens(): int
    {
        return $this->cacheReadTokens;
    }

    public function getCacheWriteTokens(): int
    {
        return $this->cacheWriteTokens;
    }

    public function getCost(): float
    {
        return $this->cost;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getTurns(): int
    {
        return $this->turns;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
