<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Dynamic;

/**
 * Callback-интерфейс для уведомления о завершении сессии оркестрации.
 *
 * Domain-сервис вызывает notifier, Application-реализация диспатчит event.
 * Симметричен RoundCompletedNotifierInterface для session-level событий.
 */
interface SessionCompletedNotifierInterface
{
    public function notifySessionCompleted(
        string $status,
        string $completionReason,
        int $totalRounds,
        float $totalTime,
        int $totalInputTokens,
        int $totalOutputTokens,
        float $totalCost,
        ?string $synthesis,
        ?string $sessionDir,
        bool $budgetExceeded = false,
        float $budgetLimit = 0.0,
        ?string $budgetExceededRole = null,
    ): void;
}
