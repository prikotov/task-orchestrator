<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Результат одного раунда dynamic-цикла: метрики, роль, статус.
 *
 * Domain-аналог Application DTO DynamicRoundResultDto.
 * Содержит только primitives — без Application зависимостей.
 *
 * Модельные поля (model/provider/thinking) и кэш-токены заполняются, если раннер
 * сообщил их; для восстановленных раундов старых сессий они равны null/0
 * (отчёт рендерит «н/д»).
 */
final readonly class DynamicRoundResultVo
{
    public function __construct(
        private int $round,
        private string $role,
        private bool $isFacilitator,
        private string $outputText,
        private int $inputTokens,
        private int $outputTokens,
        private float $cost,
        private float $duration,
        private bool $isError = false,
        private ?string $errorMessage = null,
        private ?string $invocation = null,
        private string $systemPrompt = '',
        private string $userPrompt = '',
        private bool $timedOut = false,
        private ?string $model = null,
        private ?string $provider = null,
        private ?string $thinking = null,
        private int $cacheReadTokens = 0,
        private int $cacheWriteTokens = 0,
    ) {
    }

    public function getRound(): int
    {
        return $this->round;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function isFacilitator(): bool
    {
        return $this->isFacilitator;
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

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getInvocation(): ?string
    {
        return $this->invocation;
    }

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function getUserPrompt(): string
    {
        return $this->userPrompt;
    }

    public function isTimedOut(): bool
    {
        return $this->timedOut;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function getThinking(): ?string
    {
        return $this->thinking;
    }

    public function getCacheReadTokens(): int
    {
        return $this->cacheReadTokens;
    }

    public function getCacheWriteTokens(): int
    {
        return $this->cacheWriteTokens;
    }
}
