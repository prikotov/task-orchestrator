<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Adapter;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Exception\RunnerNotFoundException;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\OrchestratorException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerRegistryPortInterface;
use Override;

/**
 * Adapter: оборачивает AgentRunnerRegistryServiceInterface, возвращает AgentRunnerPortInterface.
 *
 * Каждый AgentRunnerInterface оборачивается в AgentRunnerAdapter.
 */
final readonly class AgentRunnerRegistryAdapter implements AgentRunnerRegistryPortInterface
{
    /** @var array<string, AgentRunnerPortInterface> */
    private array $portCache;

    public function __construct(
        private AgentRunnerRegistryServiceInterface $registry,
        private RetryableRunnerFactoryInterface $retryableRunnerFactory,
        private AgentVoMapper $mapper,
    ) {
        $this->portCache = [];
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
                $runner,
                $this->retryableRunnerFactory,
                $this->mapper,
            );
        }

        return $this->portCache[$name];
    }

    #[Override]
    public function getDefault(): AgentRunnerPortInterface
    {
        $runner = $this->registry->getDefault();

        return new AgentRunnerAdapter(
            $runner,
            $this->retryableRunnerFactory,
            $this->mapper,
        );
    }

    #[Override]
    public function list(): array
    {
        $result = [];
        foreach ($this->registry->list() as $name => $runner) {
            $result[$name] = new AgentRunnerAdapter(
                $runner,
                $this->retryableRunnerFactory,
                $this->mapper,
            );
        }

        return $result;
    }
}
