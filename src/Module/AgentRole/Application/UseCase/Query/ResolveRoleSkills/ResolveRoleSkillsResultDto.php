<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills;

use TaskOrchestrator\Common\Module\AgentRole\Application\Dto\SkillDto;

/**
 * Выход UseCase резолвинга skills роли.
 *
 * Содержит упорядоченный список skills (с развёрнутыми зависимостями) и готовый
 * блок каталога (`catalogBlock`) для включения в system prompt агента.
 */
final readonly class ResolveRoleSkillsResultDto
{
    /**
     * @param list<SkillDto> $skills упорядоченный список skills роли
     * @param string $catalogBlock XML-блок `<available_skills>` (пустая строка, если skills нет)
     */
    public function __construct(
        public array $skills,
        public string $catalogBlock,
    ) {
    }
}
