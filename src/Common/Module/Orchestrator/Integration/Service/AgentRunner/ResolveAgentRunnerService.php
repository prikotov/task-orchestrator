<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Integration\Service\AgentRunner;

use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunnerByName\GetRunnerByNameQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunnerByName\GetRunnerByNameQueryHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\OrchestratorException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\ResolveAgentRunnerServiceInterface;
use Override;

/**
 * Интеграционный сервис реестра runner'ов: делегирует через AgentRunner Application use cases.
 *
 * Каждый runner оборачивается в RunAgentService.
 * Обращение к AgentRunner Domain идёт исключительно через Application-слой.
 */
final class ResolveAgentRunnerService implements ResolveAgentRunnerServiceInterface
{
    /** @var array<string, RunAgentServiceInterface> */
    private array $serviceCache = [];

    public function __construct(
        private readonly GetRunnerByNameQueryHandler $getRunnerByNameHandler,
        private readonly GetRunnersQueryHandler $getRunnersHandler,
        private readonly RunAgentCommandHandler $runAgentHandler,
        private readonly AgentDtoMapper $mapper,
    ) {
    }

    #[Override]
    public function get(string $name): RunAgentServiceInterface
    {
        if (!isset($this->serviceCache[$name])) {
            $runnerDto = $this->getRunnerByNameHandler->handle(
                new GetRunnerByNameQuery(name: $name),
            );

            if ($runnerDto === null) {
                throw new OrchestratorException(
                    sprintf('Runner "%s" not found.', $name),
                );
            }

            $this->serviceCache[$name] = new RunAgentService(
                $this->runAgentHandler,
                $this->mapper,
                $runnerDto->name,
                $runnerDto->isAvailable,
            );
        }

        return $this->serviceCache[$name];
    }

    #[Override]
    public function getDefault(): RunAgentServiceInterface
    {
        $runnerDto = $this->getRunnerByNameHandler->handle(
            new GetRunnerByNameQuery(name: null),
        );

        if ($runnerDto === null) {
            throw new OrchestratorException('Default runner not found.');
        }

        $name = $runnerDto->name;
        if (!isset($this->serviceCache[$name])) {
            $this->serviceCache[$name] = new RunAgentService(
                $this->runAgentHandler,
                $this->mapper,
                $runnerDto->name,
                $runnerDto->isAvailable,
            );
        }

        return $this->serviceCache[$name];
    }

    #[Override]
    public function list(): array
    {
        $result = [];
        $runnersResult = $this->getRunnersHandler->handle(new GetRunnersQuery());

        foreach ($runnersResult->runners as $runnerDto) {
            if (!isset($this->serviceCache[$runnerDto->name])) {
                $this->serviceCache[$runnerDto->name] = new RunAgentService(
                    $this->runAgentHandler,
                    $this->mapper,
                    $runnerDto->name,
                    $runnerDto->isAvailable,
                );
            }
            $result[$runnerDto->name] = $this->serviceCache[$runnerDto->name];
        }

        return $result;
    }
}
