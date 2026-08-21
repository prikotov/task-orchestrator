<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness;

use Override;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessActiveProbeResultDto;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessInactiveProbeResultDto;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessSnapshotDto;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessUnknownProbeResultDto;

/**
 * Явный выбор платформенной liveness-пробы по внедрённому семейству ОС.
 *
 * Linux использует procfs. Все остальные и неизвестные значения безопасно
 * выбирают UNKNOWN-реализацию без POSIX-команд и обращений к /proc.
 */
final readonly class ProcessLivenessProbeComponent implements ProcessLivenessProbeComponentInterface
{
    private ProcessLivenessProbeComponentInterface $selectedProbe;

    public function __construct(
        string $platformFamily,
        ProcessLivenessProbeComponentInterface $linuxProcfsProbe,
        ProcessLivenessProbeComponentInterface $unavailableProbe,
    ) {
        $this->selectedProbe = $platformFamily === 'Linux' ? $linuxProcfsProbe : $unavailableProbe;
    }

    #[Override]
    public function probe(
        int $processId,
        ?ProcessLivenessSnapshotDto $previousSnapshot,
    ): ProcessLivenessActiveProbeResultDto
        | ProcessLivenessInactiveProbeResultDto
        | ProcessLivenessUnknownProbeResultDto {
        return $this->selectedProbe->probe($processId, $previousSnapshot);
    }
}
