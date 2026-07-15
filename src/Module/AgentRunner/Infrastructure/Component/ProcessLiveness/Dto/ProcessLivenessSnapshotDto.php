<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto;

/**
 * Полная liveness-выборка основного PID и его непосредственных детей.
 */
final readonly class ProcessLivenessSnapshotDto
{
    public function __construct(
        /** @var array<int, ProcessLivenessPidSnapshotDto> Каноническая PID-sorted map от producer-компонента. */
        public array $processes,
    ) {
    }
}
