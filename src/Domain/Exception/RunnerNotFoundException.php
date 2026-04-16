<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Exception;

use TasK\Orchestrator\Domain\Exception\AgentException;
use TasK\Orchestrator\Domain\Exception\NotFoundExceptionInterface;

final class RunnerNotFoundException extends AgentException implements NotFoundExceptionInterface
{
    public function __construct(string $runnerName, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Agent runner "%s" not found.', $runnerName), 0, $previous);
    }
}
