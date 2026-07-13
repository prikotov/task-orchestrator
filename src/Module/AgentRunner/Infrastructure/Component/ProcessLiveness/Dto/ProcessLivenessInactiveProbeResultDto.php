<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto;

/**
 * Сопоставимая liveness-выборка без подтверждённой активности.
 */
final readonly class ProcessLivenessInactiveProbeResultDto
{
    public function __construct(
        public ProcessLivenessSnapshotDto $snapshot,
    ) {
    }
}
