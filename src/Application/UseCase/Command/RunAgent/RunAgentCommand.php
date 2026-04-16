<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Application\UseCase\Command\RunAgent;

/**
 * DTO команды запуска одного AI-агента.
 */
final readonly class RunAgentCommand
{
    public function __construct(
        public string $role,
        public string $task,
        public ?string $runner = null,
        public ?string $model = null,
        public ?string $tools = null,
        public ?string $workingDir = null,
        public int $timeout = 300,
    ) {
    }
}
