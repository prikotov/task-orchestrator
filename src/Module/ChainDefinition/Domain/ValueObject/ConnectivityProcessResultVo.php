<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

/**
 * Result (результат) процесса проверки связности роли.
 */
final readonly class ConnectivityProcessResultVo
{
    public function __construct(
        private int $exitCode,
        private string $stdout,
        private string $stderr,
        private float $durationSeconds,
        private bool $timedOut = false,
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

    public function getStderr(): string
    {
        return $this->stderr;
    }

    public function getDurationSeconds(): float
    {
        return $this->durationSeconds;
    }

    public function isTimedOut(): bool
    {
        return $this->timedOut;
    }
}
