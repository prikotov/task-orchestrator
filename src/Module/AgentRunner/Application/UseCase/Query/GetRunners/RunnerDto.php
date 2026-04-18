<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners;

/**
 * DTO информации о runner'е.
 */
final readonly class RunnerDto
{
    public function __construct(
        public string $name,
        public bool $isAvailable,
    ) {
    }
}
