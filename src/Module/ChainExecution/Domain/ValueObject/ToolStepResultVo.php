<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Value Object результата выполнения tool-шага.
 *
 * Содержит exit code, stdout и флаг успешности выполнения.
 */
final readonly class ToolStepResultVo
{
    /**
     * @param int $exitCode код завершения процесса
     * @param string $stdout стандартный вывод команды
     * @param bool $success признак успешности (exit code === 0)
     * @param float $durationMs длительность выполнения в миллисекундах
     */
    public function __construct(
        private int $exitCode,
        private string $stdout,
        private bool $success,
        private float $durationMs,
    ) {
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getStdout(): string
    {
        return $this->stdout;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }
}
