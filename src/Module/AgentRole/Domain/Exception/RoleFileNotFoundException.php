<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Exception;



/**
 * Выбрасывается, когда файл роли не найден по вычисленному пути.
 */
final class RoleFileNotFoundException extends AgentRoleException implements NotFoundExceptionInterface
{
    public function __construct(string $roleName, string $expectedPath)
    {
        parent::__construct(sprintf('Role "%s" file not found at "%s".', $roleName, $expectedPath));
    }
}
