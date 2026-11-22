<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject;

/**
 * Value Object результата выполнения AI-агента.
 *
 * Immutable, содержит выходные данные агента и метрики использования
 * (включая модель/провайдера шага и кэш-токены).
 */
final readonly class AgentResultVo
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
        private ?string $provider,
        private int $turns,
        private bool $isError,
        private ?string $errorMessage,
        private bool $timedOut = false,
    ) {
    }

    /**
     * Создаёт успешный результат.
     */
    public static function createSuccess(
        string $outputText,
        int $inputTokens = 0,
        int $outputTokens = 0,
        int $cacheReadTokens = 0,
        int $cacheWriteTokens = 0,
        float $cost = 0.0,
        ?string $model = null,
        ?string $provider = null,
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
            provider: $provider,
            turns: $turns,
            isError: false,
            errorMessage: null,
        );
    }

    /**
     * Создаёт результат с ошибкой.
     */
    public static function createError(
        string $errorMessage,
        int $exitCode = 1,
        bool $timedOut = false,
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
            provider: null,
            turns: 0,
            isError: true,
            errorMessage: $errorMessage,
            timedOut: $timedOut,
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

    /**
     * Провайдер шага (например zai, openai-codex); null — раннер не сообщил.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
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

    public function isTimedOut(): bool
    {
        return $this->timedOut;
    }
}
