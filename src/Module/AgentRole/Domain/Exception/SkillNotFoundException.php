<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Exception;



/**
 * Выбрасывается, когда skill (его каталог или SKILL.md) не найден.
 *
 * Резолвер skills работает по принципу fail-fast: если роль декларирует skill,
 * которого нет в каталоге, это ошибка конфигурации, которая должна быть
 * исправлена в источнике (frontmatter роли), а не проигнорирована.
 */
final class SkillNotFoundException extends AgentRoleException implements NotFoundExceptionInterface
{
    public function __construct(string $skillName, string $searchedDir)
    {
        parent::__construct(
            sprintf('Skill "%s" not found in "%s". Check the role frontmatter "skills:" declaration.', $skillName, $searchedDir),
        );
    }
}
