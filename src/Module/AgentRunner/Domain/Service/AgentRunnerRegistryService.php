<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\Service;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Exception\RunnerNotFoundException;
use Override;

/**
 * Реестр движков AI-агентов (Domain Service).
 *
 * Координирует name → AgentRunnerInterface. Заполняется через tagged iterator.
 */
final class AgentRunnerRegistryService implements AgentRunnerRegistryServiceInterface
{
    /** @var array<string, AgentRunnerInterface> */
    private array $runners = [];

    /**
     * @param iterable<AgentRunnerInterface> $runners
     */
    public function __construct(iterable $runners)
    {
        foreach ($runners as $runner) {
            $this->runners[$runner->getName()] = $runner;
        }
    }

    #[Override]
    public function get(string $name): AgentRunnerInterface
    {
        if (!isset($this->runners[$name])) {
            throw new RunnerNotFoundException($name);
        }

        return $this->runners[$name];
    }

    #[Override]
    public function getDefault(): AgentRunnerInterface
    {
        $first = array_key_first($this->runners);
        if ($first === null) {
            throw new RunnerNotFoundException('default');
        }

        return $this->runners[$first];
    }

    #[Override]
    public function list(): array
    {
        return $this->runners;
    }
}
