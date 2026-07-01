<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Service;

use Override;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\CircularSkillDependencyException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillNameVo;





/**
 * Реализация резолвинга skills роли через рекурсивный DFS по `depends_on`.
 *
 * Топологический порядок: зависимости добавляются раньше зависящих skills
 * (вставка в ассоциативный накопитель после обхода зависимостей сохраняет
 * порядок вставки). Циклы (A → B → A) детектятся по цепочке посещений и
 * выбрасывают {@see CircularSkillDependencyException}. Дубликаты исключаются по
 * имени skill (первое вхождение выигрывает), что соответствует конвенции
 * разрешения коллизий имён skills (agentskills.io: first-found wins).
 */
final readonly class ResolveRoleSkillsService implements ResolveRoleSkillsServiceInterface
{
    public function __construct(
        private LoadSkillFrontmatterServiceInterface $skillReader,
    ) {
    }

    /**
     * @return list<SkillMetadataVo>
     */
    #[Override]
    public function resolve(RoleMetadataVo $role): array
    {
        /** @var array<string, SkillMetadataVo> $resolved name → metadata (порядок вставки = topo) */
        $resolved = [];

        foreach ($role->getSkills() as $skillName) {
            $resolved = $this->addSkill($skillName, $resolved, []);
        }

        return array_values($resolved);
    }

    /**
     * @param array<string, SkillMetadataVo> $resolved накопитель (name → metadata)
     * @param list<string> $visiting цепочка имён от корня до текущего skill
     *
     * @return array<string, SkillMetadataVo> обновлённый накопитель
     */
    private function addSkill(SkillNameVo $skillName, array $resolved, array $visiting): array
    {
        $name = $skillName->value();

        if (array_key_exists($name, $resolved)) {
            return $resolved;
        }

        if (in_array($name, $visiting, true)) {
            throw new CircularSkillDependencyException([...$visiting, $name]);
        }

        $metadata = $this->skillReader->read($skillName);
        $visiting[] = $name;

        foreach ($metadata->getDependsOn() as $dependency) {
            $resolved = $this->addSkill($dependency, $resolved, $visiting);
        }

        // Зависимости уже в $resolved — добавляем зависящий skill после них.
        $resolved[$name] = $metadata;

        return $resolved;
    }
}
