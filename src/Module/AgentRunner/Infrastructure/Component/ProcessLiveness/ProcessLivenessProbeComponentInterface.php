<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness;

use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessActiveProbeResultDto;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessInactiveProbeResultDto;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessSnapshotDto;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto\ProcessLivenessUnknownProbeResultDto;

/**
 * Компонент получения и сопоставления платформенной liveness-выборки процесса.
 */
interface ProcessLivenessProbeComponentInterface
{
    /**
     * Получает полную выборку основного PID и его непосредственных детей.
     *
     * Ожидаемая недоступность телеметрии возвращается как UNKNOWN. Неожиданные
     * ошибки реализации распространяются наружу без нормализации.
     */
    public function probe(
        int $processId,
        ?ProcessLivenessSnapshotDto $previousSnapshot,
    ): ProcessLivenessActiveProbeResultDto
        | ProcessLivenessInactiveProbeResultDto
        | ProcessLivenessUnknownProbeResultDto;
}
