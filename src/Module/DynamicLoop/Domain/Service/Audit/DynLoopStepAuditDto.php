<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit;

/**
 * DTO статуса выполнения одного шага dynamic-цикла для audit-лога.
 */
final readonly class DynLoopStepAuditDto
{
    public function __construct(
        public bool $isError,
    ) {
    }
}
