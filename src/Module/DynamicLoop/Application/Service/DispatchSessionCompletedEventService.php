<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Application\Service;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Event\OrchestrateChain\OrchestrateSessionCompletedEvent;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\SessionCompletedNotifierInterface;

/**
 * Application-реализация: диспатчит OrchestrateSessionCompletedEvent через PSR EventDispatcher.
 */
final readonly class DispatchSessionCompletedEventService implements SessionCompletedNotifierInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Override]
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
    ): void {
        $this->eventDispatcher->dispatch(new OrchestrateSessionCompletedEvent(
            status: $status,
            completionReason: $completionReason,
            totalRounds: $totalRounds,
            totalTime: $totalTime,
            totalInputTokens: $totalInputTokens,
            totalOutputTokens: $totalOutputTokens,
            totalCost: $totalCost,
            synthesis: $synthesis,
            sessionDir: $sessionDir,
            budgetExceeded: $budgetExceeded,
            budgetLimit: $budgetLimit,
            budgetExceededRole: $budgetExceededRole,
        ));
    }
}
