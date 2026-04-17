<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Integration\Adapter;

use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunnerByName\GetRunnerByNameQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunnerByName\GetRunnerByNameQueryHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\OrchestratorException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerRegistryPortInterface;
use Override;

/**
 * ACL-адаптер реестра runner'ов: делегирует через AgentRunner Application use cases.
 *
 * Каждый runner оборачивается в AgentRunnerAdapter.
 * Обращение к AgentRunner Domain идёт исключительно через Application-слой.
 */
final class AgentRunnerRegistryAdapter implements AgentRunnerRegistryPortInterface
{
    /** @var array<string, AgentRunnerPortInterface> */
    private array $portCache = [];

    public function __construct(
        private readonly GetRunnerByNameQueryHandler $getRunnerByNameHandler,
        private readonly GetRunnersQueryHandler $getRunnersHandler,
        private readonly RunAgentCommandHandler $runAgentHandler,
        private readonly AgentDtoMapper $mapper,
    ) {
    }

    #[Override]
    public function get(string $name): AgentRunnerPortInterface
    {
        if (!isset($this->portCache[$name])) {
            $runnerDto = $this->getRunnerByNameHandler->handle(
                new GetRunnerByNameQuery(name: $name),
            );

            if ($runnerDto === null) {
                throw new OrchestratorException(
                    sprintf('Runner "%s" not found.', $name),
                );
            }

            $this->portCache[$name] = new AgentRunnerAdapter(
                $this->runAgentHandler,
                $this->mapper,
                $runnerDto->name,
                $runnerDto->isAvailable,
            );
        }

        return $this->portCache[$name];
    }

    #[Override]
    public function getDefault(): AgentRunnerPortInterface
    {
        $runnerDto = $this->getRunnerByNameHandler->handle(
            new GetRunnerByNameQuery(name: null),
        );

        if ($runnerDto === null) {
            throw new OrchestratorException('Default runner not found.');
        }

        $name = $runnerDto->name;
        if (!isset($this->portCache[$name])) {
            $this->portCache[$name] = new AgentRunnerAdapter(
                $this->runAgentHandler,
                $this->mapper,
                $runnerDto->name,
                $runnerDto->isAvailable,
            );
        }

        return $this->portCache[$name];
    }

    #[Override]
    public function list(): array
    {
        $result = [];
        $runnersResult = $this->getRunnersHandler->handle(new GetRunnersQuery());

        foreach ($runnersResult->runners as $runnerDto) {
            if (!isset($this->portCache[$runnerDto->name])) {
                $this->portCache[$runnerDto->name] = new AgentRunnerAdapter(
                    $this->runAgentHandler,
                    $this->mapper,
                    $runnerDto->name,
                    $runnerDto->isAvailable,
                );
            }
            $result[$runnerDto->name] = $this->portCache[$runnerDto->name];
        }

        return $result;
    }
}
