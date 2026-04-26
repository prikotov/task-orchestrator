<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * Результат выполнения одного шага static-цепочки (Domain VO).
 *
 * Заменяет StepExecutionResultDto + StepResultDto — устраняет дублирование полей.
 * Содержит как presentation-данные (role, runner, label), так и доменные метрики.
 */
final readonly class StaticStepResultVo
{
    public function __construct(
        public string $role,
        public string $runner,
        public string $outputText,
        public int $inputTokens,
        public int $outputTokens,
        public float $cost,
        public float $duration,
        public bool $isError,
        public ?string $errorMessage = null,
        public ?string $fallbackRunnerUsed = null,
        public ?int $iterationNumber = null,
        public bool $iterationWarning = false,
        public bool $passed = true,
        public int $exitCode = 0,
        public string $label = '',
        public bool $timedOut = false,
    ) {
    }

    public function withIterationWarning(): self
    {
        return new self(
            role: $this->role,
            runner: $this->runner,
            outputText: $this->outputText,
            inputTokens: $this->inputTokens,
            outputTokens: $this->outputTokens,
            cost: $this->cost,
            duration: $this->duration,
            isError: $this->isError,
            errorMessage: $this->errorMessage,
            fallbackRunnerUsed: $this->fallbackRunnerUsed,
            iterationNumber: $this->iterationNumber,
            iterationWarning: true,
            passed: $this->passed,
            exitCode: $this->exitCode,
            label: $this->label,
            timedOut: $this->timedOut,
        );
    }
}
