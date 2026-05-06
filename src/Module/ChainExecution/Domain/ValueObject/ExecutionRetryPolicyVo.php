<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Execution VO: политика повторных попыток для выполнения цепочки.
 *
 * Маппится из ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo через Integration-маппер.
 */
final readonly class ExecutionRetryPolicyVo
{
    // phpcs:ignore
    public function __construct(
        private int $maxRetries = 3,
        private int $initialDelayMs = 1000,
        private int $maxDelayMs = 30000,
        private float $multiplier = 2.0,
    ) {
        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must be >= 0.');
        }

        if ($initialDelayMs < 0) {
            throw new InvalidArgumentException('initialDelayMs must be >= 0.');
        }

        if ($maxDelayMs < 0) {
            throw new InvalidArgumentException('maxDelayMs must be >= 0.');
        }

        if ($multiplier < 1.0) {
            throw new InvalidArgumentException('multiplier must be >= 1.0.');
        }
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getInitialDelayMs(): int
    {
        return $this->initialDelayMs;
    }

    public function getMaxDelayMs(): int
    {
        return $this->maxDelayMs;
    }

    public function getMultiplier(): float
    {
        return $this->multiplier;
    }

    public function isEnabled(): bool
    {
        return $this->maxRetries > 0;
    }

    public function getDelayForAttempt(int $attempt): int
    {
        if ($attempt < 0) {
            return 0;
        }

        $delay = (int) ((float) $this->initialDelayMs * ($this->multiplier ** (float) $attempt));

        return min($delay, $this->maxDelayMs);
    }

    public static function createDisabled(): self
    {
        return new self(maxRetries: 0);
    }
}
