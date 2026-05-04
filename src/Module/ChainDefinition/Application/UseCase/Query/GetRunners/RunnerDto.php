<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\GetRunners;

/**
 * DTO информации о движке AI-агента.
 *
 * Транспортный объект на границе Application ↔ Presentation.
 */
final readonly class RunnerDto
{
    public function __construct(
        public string $name,
        public bool $isAvailable,
    ) {
    }
}
