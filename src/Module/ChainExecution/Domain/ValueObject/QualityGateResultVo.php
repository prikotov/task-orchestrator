<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Value Object результата выполнения quality gate.
 *
 * Содержит статус (пройден/не пройден), exit code, вывод команды и длительность.
 */
final readonly class QualityGateResultVo
{
    /**
     * @param string $label название gate
     * @param bool $passed пройден ли gate (exit code === 0)
     * @param int $exitCode код завершения процесса
     * @param string $output stdout + stderr процесса
     * @param float $durationMs длительность выполнения в миллисекундах
     */
    public function __construct(
        private string $label,
        private bool $passed,
        private int $exitCode,
        private string $output,
        private float $durationMs,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }
}
