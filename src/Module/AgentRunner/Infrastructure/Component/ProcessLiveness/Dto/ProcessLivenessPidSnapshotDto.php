<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto;

/**
 * Generation и монотонные CPU/IO-счётчики одного PID из Linux procfs.
 */
final readonly class ProcessLivenessPidSnapshotDto
{
    public function __construct(
        public int $processId,
        public int $startTimeTicks,
        public int $cpuTicks,
        public int $ioCharacters,
    ) {
    }
}
