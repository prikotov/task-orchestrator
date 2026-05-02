<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Domain\ValueObject;

/**
 * VO статуса выполнения одного шага static-цепочки для audit-лога.
 *
 * StaticExecution Domain-тип: изолирует модуль от Orchestrator DTO.
 * Маппинг в Orchestrator\StepAuditStatusDto выполняется в Integration-слое.
 */
final readonly class StaticStepAuditVo
{
    public function __construct(
        public bool $isError,
    ) {
    }
}
