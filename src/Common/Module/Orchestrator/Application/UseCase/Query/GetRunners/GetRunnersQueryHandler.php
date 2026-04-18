<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GetRunners;

use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GetRunners\RunnerDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\AgentRunnerRegistryServiceInterface;

/**
 * UseCase получения списка доступных движков AI-агентов.
 */
final readonly class GetRunnersQueryHandler
{
    public function __construct(
        private AgentRunnerRegistryServiceInterface $runnerRegistry,
    ) {
    }

    /**
     * @return list<RunnerDto>
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(GetRunnersQuery $_query): array
    {
        $runners = $this->runnerRegistry->list();
        $result = [];

        foreach ($runners as $name => $runner) {
            $result[] = new RunnerDto(
                name: $name,
                isAvailable: $runner->isAvailable(),
            );
        }

        return $result;
    }
}
