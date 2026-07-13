<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\Dto;

/**
 * Недостоверная liveness-выборка без snapshot baseline.
 */
final readonly class ProcessLivenessUnknownProbeResultDto
{
    public function __construct()
    {
    }
}
