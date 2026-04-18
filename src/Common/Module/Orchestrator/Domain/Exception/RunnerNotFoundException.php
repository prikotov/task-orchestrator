<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\AgentException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\NotFoundExceptionInterface;

final class RunnerNotFoundException extends AgentException implements NotFoundExceptionInterface
{
    public function __construct(string $runnerName, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Agent runner "%s" not found.', $runnerName), 0, $previous);
    }
}
