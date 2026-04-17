<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\CircuitStateEnum;
use InvalidArgumentException;

use function sprintf;
use function time;

/**
 * Immutable Value Object состояния Circuit Breaker для Agent Runner.
 *
 * Отслеживает три состояния (Closed → Open → HalfOpen → Closed):
 * - Closed: нормальная работа, failureCount < failureThreshold
 * - Open: вызовы блокируются, прошло меньше resetTimeoutSeconds с lastFailureAt
 * - HalfOpen: прошло resetTimeoutSeconds, разрешён один пробный вызов
 *
 * Каждая операция возвращает новый экземпляр VO (immutable).
 */
final readonly class CircuitBreakerStateVo
{
    public function __construct(
        private CircuitStateEnum $state = CircuitStateEnum::closed,
        private int $failureCount = 0,
        private int $failureThreshold = 5,
        private int $resetTimeoutSeconds = 60,
        private ?int $lastFailureAt = null,
    ) {
        if ($failureThreshold < 1) {
            throw new InvalidArgumentException('failureThreshold must be >= 1.');
        }

        if ($resetTimeoutSeconds < 1) {
            throw new InvalidArgumentException('resetTimeoutSeconds must be >= 1.');
        }

        if ($failureCount < 0) {
            throw new InvalidArgumentException('failureCount must be >= 0.');
        }
    }

    public function getState(): CircuitStateEnum
    {
        return $this->state;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    public function getResetTimeoutSeconds(): int
    {
        return $this->resetTimeoutSeconds;
    }

    public function getLastFailureAt(): ?int
    {
        return $this->lastFailureAt;
    }

    /**
     * Записывает ошибку и возвращает новое состояние.
     *
     * Closed: increment failureCount, если >= threshold → Open.
     * HalfOpen: одна ошибка → сразу Open.
     * Open: не должен вызываться (вызовы блокируются).
     */
    public function recordFailure(): self
    {
        $now = time();
        $effectiveState = $this->getEffectiveState();

        return match ($effectiveState) {
            CircuitStateEnum::closed => ($this->failureCount + 1 >= $this->failureThreshold)
                ? new self(
                    state: CircuitStateEnum::open,
                    failureCount: $this->failureCount + 1,
                    failureThreshold: $this->failureThreshold,
                    resetTimeoutSeconds: $this->resetTimeoutSeconds,
                    lastFailureAt: $now,
                )
                : new self(
                    state: CircuitStateEnum::closed,
                    failureCount: $this->failureCount + 1,
                    failureThreshold: $this->failureThreshold,
                    resetTimeoutSeconds: $this->resetTimeoutSeconds,
                    lastFailureAt: $now,
                ),
            CircuitStateEnum::halfOpen => new self(
                state: CircuitStateEnum::open,
                failureCount: $this->failureCount + 1,
                failureThreshold: $this->failureThreshold,
                resetTimeoutSeconds: $this->resetTimeoutSeconds,
                lastFailureAt: $now,
            ),
            CircuitStateEnum::open => new self(
                state: CircuitStateEnum::open,
                failureCount: $this->failureCount,
                failureThreshold: $this->failureThreshold,
                resetTimeoutSeconds: $this->resetTimeoutSeconds,
                lastFailureAt: $this->lastFailureAt,
            ),
        };
    }

    /**
     * Записывает успех и возвращает новое состояние.
     *
     * Closed: сброс failureCount (идемпотентно).
     * HalfOpen: переход в Closed с полной сброской.
     * Open: не должен вызываться (вызовы блокируются).
     */
    public function recordSuccess(): self
    {
        $effectiveState = $this->getEffectiveState();

        return match ($effectiveState) {
            CircuitStateEnum::closed, CircuitStateEnum::halfOpen => new self(
                state: CircuitStateEnum::closed,
                failureCount: 0,
                failureThreshold: $this->failureThreshold,
                resetTimeoutSeconds: $this->resetTimeoutSeconds,
                lastFailureAt: null,
            ),
            CircuitStateEnum::open => $this,
        };
    }

    /**
     * Находится ли runner в состоянии Open (вызовы блокируются)?
     *
     * Учитывает resetTimeout: если прошло достаточно времени с lastFailureAt,
     * состояние автоматически переходит в HalfOpen.
     */
    public function isOpen(): bool
    {
        if ($this->state !== CircuitStateEnum::open) {
            return false;
        }

        return !$this->isResetTimeoutElapsed();
    }

    /**
     * Находится ли runner в состоянии HalfOpen (разрешён пробный вызов)?
     */
    public function isHalfOpen(): bool
    {
        if ($this->state !== CircuitStateEnum::open) {
            return false;
        }

        return $this->isResetTimeoutElapsed();
    }

    /**
     * Возвращает «эффективное» состояние с учётом автоматического перехода Open → HalfOpen.
     *
     * Если формальное состояние Open, но resetTimeout прошёл — возвращает HalfOpen.
     */
    public function getEffectiveState(): CircuitStateEnum
    {
        if ($this->isHalfOpen()) {
            return CircuitStateEnum::halfOpen;
        }

        return $this->state;
    }

    /**
     * Создаёт VO из массива конфигурации (YAML-параметры).
     *
     * @param array{failure_threshold?: int, reset_timeout_seconds?: int} $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            failureThreshold: $config['failure_threshold'] ?? 5,
            resetTimeoutSeconds: $config['reset_timeout_seconds'] ?? 60,
        );
    }

    /**
     * Возвращает строковое представление для логирования.
     */
    public function toLogString(): string
    {
        return sprintf(
            'CircuitBreaker(state=%s, failures=%d/%d, resetTimeout=%ds, lastFailure=%s)',
            $this->getEffectiveState()->value,
            $this->failureCount,
            $this->failureThreshold,
            $this->resetTimeoutSeconds,
            $this->lastFailureAt !== null
                ? (string) $this->lastFailureAt
                : 'null',
        );
    }

    private function isResetTimeoutElapsed(): bool
    {
        if ($this->lastFailureAt === null) {
            return true;
        }

        return (time() - $this->lastFailureAt) >= $this->resetTimeoutSeconds;
    }
}
