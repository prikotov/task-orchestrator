<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Exception;

use TasK\Orchestrator\Domain\Exception\AgentException;
use TasK\Orchestrator\Domain\Exception\NotFoundExceptionInterface;

final class ChainNotFoundException extends AgentException implements NotFoundExceptionInterface
{
    public function __construct(string $chainName, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Chain "%s" not found.', $chainName), 0, $previous);
    }
}
