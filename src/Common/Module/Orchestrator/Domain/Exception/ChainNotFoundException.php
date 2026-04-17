<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Exception\AgentException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\NotFoundExceptionInterface;

final class ChainNotFoundException extends AgentException implements NotFoundExceptionInterface
{
    public function __construct(string $chainName, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Chain "%s" not found.', $chainName), 0, $previous);
    }
}
