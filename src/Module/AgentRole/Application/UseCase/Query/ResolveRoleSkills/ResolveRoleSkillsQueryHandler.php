<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills;

use Symfony\Component\Filesystem\Path;
use TaskOrchestrator\Common\Module\AgentRole\Application\Dto\SkillDto;
use TaskOrchestrator\Common\Module\AgentRole\Application\Exception\ResolveRoleSkillsFailedException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\AgentRoleException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\FormatSkillCatalogServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LoadRoleFrontmatterServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LocateRoleFileServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\ResolveRoleSkillsServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleNameVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillMetadataVo;

/**
 * Оркестрация резолвинга skills роли.
 *
 * Контракт:
 *   1. вычислить имя роли (RoleNameVo);
 *   2. найти файл роли (locator);
 *   3. прочитать frontmatter роли → декларация skills (reader);
 *   4. развернуть skills с зависимостями (resolver);
 *   5. отформатировать каталог (formatter);
 *   6. вернуть DTO (skills + готовый блок каталога).
 *
 * Boundary: доменные {@see AgentRoleException} оборачиваются в
 * {@see ResolveRoleSkillsFailedException}, чтобы Presentation не зависел от Domain.
 */
final readonly class ResolveRoleSkillsQueryHandler
{
    public function __construct(
        private LocateRoleFileServiceInterface $roleFileLocator,
        private LoadRoleFrontmatterServiceInterface $roleFrontmatterReader,
        private ResolveRoleSkillsServiceInterface $roleSkillsResolver,
        private FormatSkillCatalogServiceInterface $skillCatalogFormatter,
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
        $roleName = RoleNameVo::createFromName($query->roleName);
        $roleFile = $this->roleFileLocator->locate($roleName);
        $roleMetadata = $this->roleFrontmatterReader->read($roleFile);
        $skills = $this->roleSkillsResolver->resolve($roleMetadata);
        $catalogBlock = $this->skillCatalogFormatter->format($skills);

        return new ResolveRoleSkillsResultDto(
            skills: $this->toSkillDtos($skills),
            catalogBlock: $catalogBlock,
            roleFilePath: $this->relativeRoleFilePath($roleFile),
        );
    }

    /**
     * Относительный путь файла роли от base_path проекта.
     */
    private function relativeRoleFilePath(string $roleFile): string
    {
        return Path::makeRelative($roleFile, $this->basePath);
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
