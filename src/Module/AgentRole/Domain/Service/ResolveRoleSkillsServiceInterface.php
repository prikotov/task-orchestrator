<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Service;

use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillMetadataVo;

/**
 * Резолвит полный список skills роли с развёрткой транзитивных зависимостей.
 *
 * Доменный контракт: берёт декларацию skills роли из {@see RoleMetadataVo} и
 * разворачивает граф зависимостей (`depends_on` в frontmatter каждого skill).
 * Зависимости помещаются перед зависящими от них skills (топологический
 * порядок), дубликаты исключаются по имени skill.
 */
interface ResolveRoleSkillsServiceInterface
{
    /**
     * @return list<SkillMetadataVo> упорядоченный список skills роли (с зависимостями)
     */
    public function resolve(RoleMetadataVo $role): array;
}
