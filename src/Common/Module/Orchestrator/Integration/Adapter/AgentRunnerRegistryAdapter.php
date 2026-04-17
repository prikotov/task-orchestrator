<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Integration\Adapter;

use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommand;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentResultDto;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Exception\RunnerNotFoundException;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\OrchestratorException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerRegistryPortInterface;
use Override;

/**
 * ACL-адаптер реестра runner'ов: делегирует через AgentRunner Application use cases.
 *
 * Каждый AgentRunnerInterface оборачивается в AgentRunnerAdapter.
 */
final class AgentRunnerRegistryAdapter implements AgentRunnerRegistryPortInterface
{
    /** @var array<string, AgentRunnerPortInterface> */
    private array $portCache = [];

    public function __construct(
        private readonly GetRunnersQueryHandler $getRunnersHandler,
        private readonly RunAgentCommandHandler $runAgentHandler,
        private readonly AgentDtoMapper $mapper,
        private readonly AgentRunnerRegistryServiceInterface $registry,
    ) {
    }

    #[Override]
    public function get(string $name): AgentRunnerPortInterface
    {
        if (!isset($this->portCache[$name])) {
            try {
                $runner = $this->registry->get($name);
            } catch (RunnerNotFoundException $e) {
                throw new OrchestratorException(
                    sprintf('Runner "%s" not found.', $name),
                    0,
                    $e,
                );
            }
            $this->portCache[$name] = new AgentRunnerAdapter(
                $this->runAgentHandler,
                $this->mapper,
                $runner->getName(),
                $runner->isAvailable(),
            );
        }

        return $this->portCache[$name];
    }

    #[Override]
    public function getDefault(): AgentRunnerPortInterface
    {
        $runner = $this->registry->getDefault();
        $name = $runner->getName();

        if (!isset($this->portCache[$name])) {
            $this->portCache[$name] = new AgentRunnerAdapter(
                $this->runAgentHandler,
                $this->mapper,
                $runner->getName(),
                $runner->isAvailable(),
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
