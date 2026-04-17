<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\Exception;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Exception\AgentException;

final class RunnerNotFoundException extends AgentException
{
    public function __construct(string $runnerName, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Agent runner "%s" not found.', $runnerName), 0, $previous);
    }
}
