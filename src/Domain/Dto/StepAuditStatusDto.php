<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Dto;

/**
 * DTO статуса выполнения одного шага цепочки для audit-лога.
 *
 * Используется в Domain-слое для передачи информации об ошибке/успехе шага
 * при логировании финального результата цепочки.
 */
final readonly class StepAuditStatusDto
{
    public function __construct(
        public bool $isError,
    ) {
    }
}
