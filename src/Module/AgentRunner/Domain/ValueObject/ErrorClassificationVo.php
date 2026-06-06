<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Enum\ErrorClassificationEnum;
use Throwable;

/**
 * Value Object — результат классификации ошибки агента.
 *
 * Immutable VO, определяет стратегию retry на основе полей AgentResultVo:
 * - isTimedOut()        → TRANSIENT  (network issue, retry имеет смысл)
 * - exitCode >= 100     → FATAL      (process-level crash, retry бессмысленен)
 * - exitCode == 0 + isError → UNKNOWN (аномалия, retry консервативно)
 * - exitCode > 0 && < 100 → TRANSIENT (обычная ошибка, retry по умолчанию)
 *
 * Классификация по полям, НЕ по тексту ошибки.
 */
final readonly class ErrorClassificationVo
{
    private function __construct(
        private ErrorClassificationEnum $classification,
    ) {
    }

    /**
     * Классифицирует результат выполнения агента.
     *
     * Правила применяются в порядке приоритета:
     * 1. isTimedOut() == true  → TRANSIENT
     * 2. exitCode >= 100       → FATAL
     * 3. exitCode == 0 + error → UNKNOWN
     * 4. exitCode > 0 && < 100 → TRANSIENT (default для обычных ошибок)
     */
    public static function createFromClassification(AgentResultVo $result): self
    {
        if ($result->isTimedOut()) {
            return new self(ErrorClassificationEnum::transient);
        }

        $exitCode = $result->getExitCode();

        if ($exitCode >= 100) {
            return new self(ErrorClassificationEnum::fatal);
        }

        if ($exitCode === 0 && $result->isError()) {
            return new self(ErrorClassificationEnum::unknown);
        }

        return new self(ErrorClassificationEnum::transient);
    }

    /**
     * Классифицирует исключение, выброшенное при выполнении агента.
     *
     * Исключения из runner'а классифицируются как TRANSIENT —
     * причина обычно во внешних факторах (сеть, процесс упал и т.д.).
     */
    public static function createFromClassException(Throwable $throwable): self
    {
        return new self(
            match ($throwable::class) {
                default => ErrorClassificationEnum::transient,
            },
        );
    }

    public function getClassification(): ErrorClassificationEnum
    {
        return $this->classification;
    }

    /**
     * Следует ли повторять попытку при данной классификации?
     *
     * FATAL → false (retry бессмысленен)
     * TRANSIENT/UNKNOWN → true (retry оправдан)
     */
    public function shouldRetry(): bool
    {
        return $this->classification !== ErrorClassificationEnum::fatal;
    }

    public function equals(self $other): bool
    {
        return $this->classification === $other->classification;
    }
}
