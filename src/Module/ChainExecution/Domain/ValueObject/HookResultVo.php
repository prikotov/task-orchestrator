<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Value Object результата выполнения post_step hook.
 *
 * Immutable VO: описывает результат запуска shell-скрипта после шага цепочки.
 * Три состояния: success (exit code 0), warning (exit code != 0 или timeout), skipped (hook не сконфигурирован).
 */
final readonly class HookResultVo
{
    private function __construct(
        private string $command,
        private int $exitCode,
        private string $stdout,
        private string $stderr,
        private float $duration,
        private bool $timedOut,
        private bool $success,
        private bool $skipped,
        private ?string $warningReason = null,
    ) {
    }

    /**
     * Создаёт успешный результат hook (exit code 0).
     */
    public static function success(
        string $command,
        string $stdout,
        string $stderr,
        float $duration,
    ): self {
        return new self(
            command: $command,
            exitCode: 0,
            stdout: $stdout,
            stderr: $stderr,
            duration: $duration,
            timedOut: false,
            success: true,
            skipped: false,
        );
    }

    /**
     * Создаёт результат hook с warning (exit code != 0 или timeout).
     *
     * Hook failure не прерывает цепочку — только warning.
     */
    public static function warning(
        string $command,
        int $exitCode,
        string $stdout,
        string $stderr,
        float $duration,
        bool $timedOut,
        string $reason,
    ): self {
        return new self(
            command: $command,
            exitCode: $exitCode,
            stdout: $stdout,
            stderr: $stderr,
            duration: $duration,
            timedOut: $timedOut,
            success: false,
            skipped: false,
            warningReason: $reason,
        );
    }

    /**
     * Создаёт результат «hook не сконфигурирован» (skipped).
     */
    public static function skipped(): self
    {
        return new self(
            command: '',
            exitCode: 0,
            stdout: '',
            stderr: '',
            duration: 0.0,
            timedOut: false,
            success: false,
            skipped: true,
        );
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getStdout(): string
    {
        return $this->stdout;
    }

    public function getStderr(): string
    {
        return $this->stderr;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function isTimedOut(): bool
    {
        return $this->timedOut;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isSkipped(): bool
    {
        return $this->skipped;
    }

    public function getWarningReason(): ?string
    {
        return $this->warningReason;
    }

    /**
     * Является ли результатом warning (hook упал или timed out).
     */
    public function isWarning(): bool
    {
        return !$this->success && !$this->skipped;
    }
}
