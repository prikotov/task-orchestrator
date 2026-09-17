<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills;

/**
 * Вход UseCase резолвинга skills роли.
 *
 * Несёт аргумент роли — имя (snake_case, как в `config/chains.yaml`
 * `roles.<role>` и имя файла роли без локали) ИЛИ путь к файлу роли
 * (абсолютный или относительный от одного из базисов резолвинга).
 */
final readonly class ResolveRoleSkillsQuery
{
    /**
     * @param list<string> $pathBases базисы для относительных путей к файлу роли
     *                                (абсолютные каталоги; например, cwd процесса и
     *                                логический PWD вызывающего шелла)
     */
    public function __construct(
        public string $role,
        public array $pathBases = [],
    ) {
    }
}
