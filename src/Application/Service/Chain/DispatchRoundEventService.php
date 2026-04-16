<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Application\Service\Chain;

use TasK\Orchestrator\Application\Event\OrchestrateChain\OrchestrateRoundCompletedEvent;
use TasK\Orchestrator\Domain\Service\Chain\RoundCompletedNotifierInterface;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Application-реализация: диспатчит OrchestrateRoundCompletedEvent через PSR EventDispatcher.
 */
final readonly class DispatchRoundEventService implements RoundCompletedNotifierInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Override]
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
    ): void {
        $this->eventDispatcher->dispatch(new OrchestrateRoundCompletedEvent(
            step: $step,
            round: $round,
            role: $role,
            isFacilitator: $isFacilitator,
            isError: $isError,
            errorMessage: $errorMessage,
            duration: $duration,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $cost,
            nextRole: $nextRole,
            done: $done,
            synthesis: $synthesis,
        ));
    }
}
