<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\NotFoundExceptionInterface;

final class RoleNotFoundException extends OrchestratorException implements NotFoundExceptionInterface
{
    public function __construct(string $role, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Agent role "%s" not found.', $role), 0, $previous);
    }
}
