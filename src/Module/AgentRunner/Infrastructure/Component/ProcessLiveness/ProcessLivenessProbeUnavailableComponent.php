<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness;

use Override;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessSnapshotDto;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessUnknownProbeResultDto;

/**
 * Безопасная UNKNOWN-проба для платформ без поддерживаемой телеметрии.
 */
final readonly class ProcessLivenessProbeUnavailableComponent implements ProcessLivenessProbeComponentInterface
{
    #[Override]
    public function probe(
        int $processId,
        ?ProcessLivenessSnapshotDto $previousSnapshot,
    ): ProcessLivenessUnknownProbeResultDto {
        return new ProcessLivenessUnknownProbeResultDto();
    }
}
