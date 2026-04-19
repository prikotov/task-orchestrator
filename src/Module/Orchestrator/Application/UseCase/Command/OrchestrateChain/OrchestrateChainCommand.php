<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain;

/**
 * DTO команды оркестрации цепочки AI-агентов.
 */
final readonly class OrchestrateChainCommand
{
    public function __construct(
        public string $chainName,
        public string $task,
        public ?string $runner = null,
        public ?string $model = null,
        public ?string $workingDir = null,
        public int $timeout = 300,
        public ?string $topic = null,
        public ?int $maxRounds = null,
        public ?string $facilitator = null,
        /**
         * @var list<string>|null
         */
        public ?array $participants = null,
        public ?string $resumeDir = null,
        public bool $noAuditLog = false,
        public bool $noContextFiles = false,
    ) {
    }
}
