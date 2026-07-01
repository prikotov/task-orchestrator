<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills;

/**
 * Вход UseCase резолвинга skills роли.
 *
 * Несёт имя роли (snake_case, как в `config/chains.yaml` `roles.<role>` и имя
 * файла роли без локали).
 */
final readonly class ResolveRoleSkillsQuery
{
    public function __construct(public string $roleName)
    {
    }
}
