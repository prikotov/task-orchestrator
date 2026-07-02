<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service;

use InvalidArgumentException;
use Override;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LoadRoleFrontmatterServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleNameVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillNameVo;
use TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Component\Frontmatter\FrontmatterYamlParser;






/**
 * Читает метаданные роли из YAML-frontmatter файла роли.
 *
 * Извлекает декларацию skills (поле `skills:` — список строк) и строит
 * {@see RoleMetadataVo}. Имя роли выводится из имени файла.
 */
final readonly class YamlLoadRoleFrontmatterService implements LoadRoleFrontmatterServiceInterface
{
    public function __construct(
        private FrontmatterYamlParser $frontmatterParser,
    ) {
    }

    #[Override]
    public function read(string $filePath): RoleMetadataVo
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidArgumentException(sprintf('Cannot read role file: %s', $filePath));
        }

        $data = $this->frontmatterParser->parse($content);
        $skills = $this->extractSkills($data, $filePath);

        return new RoleMetadataVo(
            name: RoleNameVo::createFromFileName($filePath),
            filePath: $filePath,
            skills: $skills,
        );
    }

    /**
     * @param array<non-empty-string, mixed> $data
     *
     * @return list<SkillNameVo>
     */
    private function extractSkills(array $data, string $filePath): array
    {
        if (!array_key_exists('skills', $data)) {
            return [];
        }

        $rawSkills = $data['skills'];
        if (!is_array($rawSkills)) {
            throw new InvalidArgumentException(sprintf('Role "%s" frontmatter "skills:" must be a list.', $filePath));
        }

        $skills = [];
        foreach ($rawSkills as $skillName) {
            if (!is_string($skillName) || $skillName === '') {
                throw new InvalidArgumentException(
                    sprintf('Role "%s" frontmatter "skills:" contains a non-string entry.', $filePath),
                );
            }

            $skills[] = SkillNameVo::createFromName($skillName);
        }

        return $skills;
    }
}
