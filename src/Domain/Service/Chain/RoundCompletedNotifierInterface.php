<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

/**
 * Callback-интерфейс для уведомления о завершении раунда dynamic-цикла.
 *
 * Domain-сервис вызывает notifier, Application-реализация диспатчит event.
 * Это позволяет Domain не зависеть от PSR EventDispatcher и Application Event.
 */
interface RoundCompletedNotifierInterface
{
    public function notifyRoundCompleted(
        int $step,
        int $round,
        string $role,
        bool $isFacilitator,
        bool $isError,
        ?string $errorMessage,
        float $duration,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        ?string $nextRole = null,
        bool $done = false,
        ?string $synthesis = null,
    ): void;
}
