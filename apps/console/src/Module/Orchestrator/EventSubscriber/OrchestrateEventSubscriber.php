<?php

declare(strict_types=1);

namespace Console\Module\Orchestrator\EventSubscriber;

use Override;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Event\OrchestrateChain\OrchestrateRoundCompletedEvent;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Event\OrchestrateChain\OrchestrateSessionCompletedEvent;

/**
 * Подписчик событий оркестрации — вывод прогресса в консоль.
 */
final class OrchestrateEventSubscriber implements EventSubscriberInterface
{
    private ?SymfonyStyle $io = null;

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onCommand',
            OrchestrateRoundCompletedEvent::class => 'onRound',
            OrchestrateSessionCompletedEvent::class => 'onSession',
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $this->io = new SymfonyStyle($event->getInput(), $event->getOutput());
    }

    public function onRound(OrchestrateRoundCompletedEvent $event): void
    {
        if ($this->io === null) {
            return;
        }

        $tag = $event->isFacilitator ? '🎯' : '👤';
        $status = $event->isError ? '✗' : '✓';
        $duration = round($event->duration);

        $line = sprintf(
            '[S%d/R%d] %s %s ... %s (↑%s ↓%s $%.4f, %ds)',
            $event->step,
            $event->round,
            $tag,
            $event->role,
            $status,
            $this->formatTokens($event->inputTokens),
            $this->formatTokens($event->outputTokens),
            $event->cost,
            $duration,
        );

        if ($event->isError) {
            $line .= sprintf(' ERROR: %s', $event->errorMessage ?? 'Unknown');
        } elseif ($event->done) {
            $line .= ' → done';
        } elseif ($event->nextRole !== null) {
            $line .= sprintf(' → next: %s', $event->nextRole);
        }

        $this->io->text($line);
    }

    public function onSession(OrchestrateSessionCompletedEvent $event): void
    {
        if ($this->io === null) {
            return;
        }

        $this->io->newLine();

        if ($event->status === 'completed') {
            $this->io->success(sprintf(
                '✅ %s (%s) | Rounds: %d | %.1fs | ↑%s ↓%s $%.4f',
                $event->status,
                $event->completionReason ?? '?',
                $event->totalRounds,
                $event->totalTime,
                $this->formatTokens($event->totalInputTokens),
                $this->formatTokens($event->totalOutputTokens),
                $event->totalCost,
            ));

            if ($event->synthesis !== null && $event->synthesis !== '') {
                $this->io->section('📝 Synthesis');
                $this->io->text($event->synthesis);
            }
        } else {
            $this->io->warning(sprintf(
                '⚠️ %s (%s) | Rounds: %d | %.1fs | ↑%s ↓%s $%.4f',
                $event->status,
                $event->completionReason ?? '?',
                $event->totalRounds,
                $event->totalTime,
                $this->formatTokens($event->totalInputTokens),
                $this->formatTokens($event->totalOutputTokens),
                $event->totalCost,
            ));
        }

        if ($event->sessionDir !== null) {
            $this->io->note(sprintf('Session dir: %s', $event->sessionDir));
        }
    }

    private function formatTokens(int $tokens): string
    {
        if ($tokens >= 1000) {
            return sprintf('%.1fk', $tokens / 1000);
        }

        return (string) $tokens;
    }
}
