<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GetRunners;

use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQuery as AgentRunnerGetRunnersQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler as AgentRunnerGetRunnersQueryHandler;

/**
 * UseCase получения списка доступных движков AI-агентов.
 */
final readonly class GetRunnersQueryHandler
{
    public function __construct(
        private AgentRunnerGetRunnersQueryHandler $getRunnersHandler,
    ) {
    }

    /**
     * @return list<RunnerDto>
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(GetRunnersQuery $_query): array
    {
        $agentRunnerResult = ($this->getRunnersHandler)(new AgentRunnerGetRunnersQuery());

        return array_map(
            static fn(\TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\RunnerDto $dto): RunnerDto => new RunnerDto(
                name: $dto->name,
                isAvailable: $dto->isAvailable,
            ),
            $agentRunnerResult->runners,
        );
    }
}
