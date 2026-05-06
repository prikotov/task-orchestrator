<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Integration\Service\ChainExecution;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Event\OrchestrateChain\OrchestrateRoundCompletedEvent;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\RoundCompletedNotifierInterface;

/**
 * Integration-реализация: диспатчит OrchestrateRoundCompletedEvent через PSR EventDispatcher.
 *
 * Расположен в Integration-слое, т.к. создаёт Event из чужого Application (разрешено: Integration → foreign Application).
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
