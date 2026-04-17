<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

use InvalidArgumentException;

use function sprintf;

/**
 * Value Object политики повторных попыток (retry) для Orchestrator Port.
 *
 * Orchestrator Domain VO — дубликат AgentRunner\RetryPolicyVo.
 * Маппинг в RetryPolicyVo выполняется в Infrastructure Adapter.
 *
 * Immutable VO, задаёт параметры exponential backoff:
 * - maxRetries — максимальное количество повторных попыток (0 = retry выключен)
 * - initialDelayMs — начальная задержка в миллисекундах
 * - maxDelayMs — максимальная задержка в миллисекундах
 * - multiplier — множитель exponential backoff
 *
 * Формула задержки: min(initialDelayMs * multiplier^attempt, maxDelayMs)
 */
final readonly class ChainRetryPolicyVo
{
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

    /**
     * Включены ли повторные попытки?
     */
    public function isEnabled(): bool
    {
        return $this->maxRetries > 0;
    }

    /**
     * Вычисляет задержку в миллисекундах для указанной попытки (0-based).
     *
     * Формула: min(initialDelayMs * multiplier^attempt, maxDelayMs)
     *
     * @param int $attempt номер попытки (0-based: 0 = первая повторная попытка)
     *
     * @return int задержка в миллисекундах
     */
    public function getDelayForAttempt(int $attempt): int
    {
        if ($attempt < 0) {
            return 0;
        }

        $delay = (int) ((float) $this->initialDelayMs * ($this->multiplier ** (float) $attempt));

        return min($delay, $this->maxDelayMs);
    }

    /**
     * Создаёт политику с выключенным retry (0 попыток).
     */
    public static function disabled(): self
    {
        return new self(maxRetries: 0);
    }

    /**
     * Создаёт политику из массива конфигурации (YAML-параметры).
     *
     * @param array{max_retries?: int, initial_delay_ms?: int, max_delay_ms?: int, multiplier?: float} $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            maxRetries: $config['max_retries'] ?? 3,
            initialDelayMs: $config['initial_delay_ms'] ?? 1000,
            maxDelayMs: $config['max_delay_ms'] ?? 30000,
            multiplier: $config['multiplier'] ?? 2.0,
        );
    }

    /**
     * Возвращает строковое представление для логирования.
     */
    public function toLogString(): string
    {
        return sprintf(
            'ChainRetryPolicy(maxRetries=%d, initialDelayMs=%d, maxDelayMs=%d, multiplier=%.1f)',
            $this->maxRetries,
            $this->initialDelayMs,
            $this->maxDelayMs,
            $this->multiplier,
        );
    }
}
