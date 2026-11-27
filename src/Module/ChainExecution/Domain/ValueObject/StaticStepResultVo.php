<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Результат выполнения одного шага static-цепочки (Domain VO).
 *
 * Заменяет StepExecutionResultDto + StepResultDto — устраняет дублирование полей.
 * Содержит как presentation-данные (role, runner, label), так и доменные метрики.
 */
final readonly class StaticStepResultVo
{
    public function __construct(
        private string $role,
        private string $runner,
        private string $outputText,
        private int $inputTokens,
        private int $outputTokens,
        private float $cost,
        private float $duration,
        private bool $isError,
        private ?string $errorMessage = null,
        private ?string $fallbackRunnerUsed = null,
        private ?int $iterationNumber = null,
        private bool $iterationWarning = false,
        private bool $passed = true,
        private int $exitCode = 0,
        private string $label = '',
        private bool $timedOut = false,
        private ?string $outputKey = null,
    ) {
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getRunner(): string
    {
        return $this->runner;
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

    public function getFallbackRunnerUsed(): ?string
    {
        return $this->fallbackRunnerUsed;
    }

    public function getIterationNumber(): ?int
    {
        return $this->iterationNumber;
    }

    public function isIterationWarning(): bool
    {
        return $this->iterationWarning;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isTimedOut(): bool
    {
        return $this->timedOut;
    }

    public function getOutputKey(): ?string
    {
        return $this->outputKey;
    }
}
