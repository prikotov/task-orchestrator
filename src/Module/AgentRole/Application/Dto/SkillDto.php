<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Application\Dto;

/**
 * Транспортное представление skill на границе слоёв.
 *
 * Используется в {@see \TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills\ResolveRoleSkillsResultDto}
 * для перечисления skills роли без раскрытия доменных VO наружу.
 */
final readonly class SkillDto
{
    public function __construct(
        public string $name,
        public string $description,
        public string $location,
    ) {
    }
}
