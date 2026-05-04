<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Exception;

/**
 * Role prompt file not found.
 */
final class RoleNotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{
    public function __construct(string $role, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Role "%s" not found.', $role), $code, $previous);
    }
}
