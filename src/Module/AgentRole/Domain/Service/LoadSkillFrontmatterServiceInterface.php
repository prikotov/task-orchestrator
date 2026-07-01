<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Service;

use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\SkillNotFoundException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillNameVo;

/**
 * Читает метаданные skill из frontmatter файла SKILL.md.
 *
 * Инфраструктурный контракт: реализация резолвит путь к skill по имени
 * (`skills_dir/<name>/SKILL.md`) и парсит его frontmatter. Возвращает поля,
 * необходимые для построения каталога skills: name, description, location,
 * depends_on.
 */
interface LoadSkillFrontmatterServiceInterface
{
    /**
     * @throws SkillNotFoundException если skill (каталог или SKILL.md) не найден
     */
    public function read(SkillNameVo $skillName): SkillMetadataVo;
}
