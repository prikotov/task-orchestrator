<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Exception;

use TasK\Orchestrator\Domain\Exception\AgentException;
use TasK\Orchestrator\Domain\Exception\NotFoundExceptionInterface;

final class RoleNotFoundException extends AgentException implements NotFoundExceptionInterface
{
    public function __construct(string $role, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Agent role "%s" not found.', $role), 0, $previous);
    }
}
