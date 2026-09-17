<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills;

use Symfony\Component\Filesystem\Filesystem;
use TaskOrchestrator\Common\Module\AgentRole\Application\Dto\SkillDto;
use TaskOrchestrator\Common\Module\AgentRole\Application\Exception\ResolveRoleSkillsFailedException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\AgentRoleException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\FormatSkillCatalogServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LoadRoleFrontmatterServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\ResolveRoleArgumentServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\ResolveRoleSkillsServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillMetadataVo;

/**
 * Оркестрация резолвинга skills роли.
 *
 * Контракт:
 *   1. резолвить аргумент роли (имя/путь) в файл роли (argument resolver);
 *   2. прочитать frontmatter роли → декларация skills (reader);
 *   3. развернуть skills с зависимостями (resolver);
 *   4. отформатировать каталог (formatter);
 *   5. вернуть DTO (имя роли, skills + готовый блок каталога).
 *
 * Boundary: доменные {@see AgentRoleException} оборачиваются в
 * {@see ResolveRoleSkillsFailedException}, чтобы Presentation не зависел от Domain.
 */
final readonly class ResolveRoleSkillsQueryHandler
{
    public function __construct(
        private ResolveRoleArgumentServiceInterface $roleArgumentResolver,
        private LoadRoleFrontmatterServiceInterface $roleFrontmatterReader,
        private ResolveRoleSkillsServiceInterface $roleSkillsResolver,
        private FormatSkillCatalogServiceInterface $skillCatalogFormatter,
        private Filesystem $filesystem,
        private string $basePath,
    ) {
    }

    /**
     * @throws ResolveRoleSkillsFailedException при любой доменной ошибке резолвинга.
     */
    public function __invoke(ResolveRoleSkillsQuery $query): ResolveRoleSkillsResultDto
    {
        try {
            return $this->handle($query);
        } catch (AgentRoleException $e) {
            throw ResolveRoleSkillsFailedException::fromDomain($e);
        }
    }

    private function handle(ResolveRoleSkillsQuery $query): ResolveRoleSkillsResultDto
    {
        $roleFile = $this->roleArgumentResolver->resolve($query->role, $query->pathBases);
        $roleMetadata = $this->roleFrontmatterReader->read($roleFile);
        $skills = $this->roleSkillsResolver->resolve($roleMetadata);
        $catalogBlock = $this->skillCatalogFormatter->format($skills);

        return new ResolveRoleSkillsResultDto(
            skills: $this->toSkillDtos($skills),
            catalogBlock: $catalogBlock,
            roleName: $this->roleNameFromFilePath($roleFile),
            roleFilePath: $this->relativeRoleFilePath($roleFile),
        );
    }

    /**
     * Имя роли из basename файла роли: team_lead_alex.ru.md → team_lead_alex.
     */
    private function roleNameFromFilePath(string $roleFile): string
    {
        $name = basename($roleFile);
        $name = preg_replace('/\.md$/', '', $name) ?? $name;

        return preg_replace('/\.[a-z]{2}$/', '', $name) ?? $name;
    }

    /**
     * Относительный путь файла роли от base_path проекта.
     */
    private function relativeRoleFilePath(string $roleFile): string
    {
        // makePathRelative добавляет trailing '/' (для директорий) — обрезаем для файла.
        return rtrim($this->filesystem->makePathRelative($roleFile, $this->basePath), '/');
    }

    /**
     * @param list<SkillMetadataVo> $skills
     *
     * @return list<SkillDto>
     */
    private function toSkillDtos(array $skills): array
    {
        return array_map(
            static function (SkillMetadataVo $skill): SkillDto {
                return new SkillDto(
                    name: $skill->getName()->value(),
                    description: $skill->getDescription(),
                    location: $skill->getLocation(),
                );
            },
            $skills,
        );
    }
}
