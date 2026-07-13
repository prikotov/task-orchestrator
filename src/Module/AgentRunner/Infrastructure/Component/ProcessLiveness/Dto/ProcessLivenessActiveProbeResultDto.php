<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto;

/**
 * Сопоставимая liveness-выборка с подтверждённой активностью.
 */
final readonly class ProcessLivenessActiveProbeResultDto
{
    public function __construct(
        public ProcessLivenessSnapshotDto $snapshot,
    ) {
    }
}
