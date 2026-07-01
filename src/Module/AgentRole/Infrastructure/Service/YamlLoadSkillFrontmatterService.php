<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service;

use InvalidArgumentException;
use Override;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\SkillNotFoundException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LoadSkillFrontmatterServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillMetadataVo;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\SkillNameVo;
use TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Component\Frontmatter\FrontmatterYamlParser;










/**
 * Читает метаданные skill из YAML-frontmatter файла SKILL.md.
 *
 * Резолвит путь к skill по имени: `<skills_dir>/<name>/SKILL.md`. Имя skill
 * берётся из frontmatter (поле `name`) с фолбэком на переданное имя (каталог),
 * как делают pi и codex.
 */
final readonly class YamlLoadSkillFrontmatterService implements LoadSkillFrontmatterServiceInterface
{
    public function __construct(
        private string $skillsDir,
        private FrontmatterYamlParser $frontmatterParser,
    ) {
    }

    #[Override]
    public function read(SkillNameVo $skillName): SkillMetadataVo
    {
        $skillDir = rtrim($this->skillsDir, '/') . '/' . $skillName->value();
        $skillFile = $skillDir . '/SKILL.md';

        if (!is_file($skillFile)) {
            throw new SkillNotFoundException($skillName->value(), $this->skillsDir);
        }

        $content = file_get_contents($skillFile);
        if ($content === false) {
            throw new InvalidArgumentException(sprintf('Cannot read skill file: %s', $skillFile));
        }

        $realPath = realpath($skillFile);
        $location = $realPath !== false ? $realPath : $skillFile;

        $data = $this->frontmatterParser->parse($content);

        $name = $this->resolveName($data, $skillName);
        $description = $this->extractDescription($data, $skillFile);
        $dependsOn = $this->extractDependsOn($data, $skillFile);

        return new SkillMetadataVo(
            name: $name,
            description: $description,
            location: $location,
            dependsOn: $dependsOn,
        );
    }

    /**
     * @param array<non-empty-string, mixed> $data
     */
    private function resolveName(array $data, SkillNameVo $fallback): SkillNameVo
    {
        if (array_key_exists('name', $data) && is_string($data['name']) && $data['name'] !== '') {
            return SkillNameVo::createFromName($data['name']);
        }

        return $fallback;
    }

    /**
     * @param array<non-empty-string, mixed> $data
     */
    private function extractDescription(array $data, string $skillFile): string
    {
        $description = $data['description'] ?? null;
        if (!is_string($description) || trim($description) === '') {
            throw new InvalidArgumentException(
                sprintf('Skill "%s" is missing required non-empty "description" in frontmatter.', $skillFile),
            );
        }

        return $description;
    }

    /**
     * @param array<non-empty-string, mixed> $data
     *
     * @return list<SkillNameVo>
     */
    private function extractDependsOn(array $data, string $skillFile): array
    {
        if (!array_key_exists('depends_on', $data)) {
            return [];
        }

        $rawDependencies = $data['depends_on'];
        if (!is_array($rawDependencies)) {
            throw new InvalidArgumentException(
                sprintf('Skill "%s" frontmatter "depends_on:" must be a list.', $skillFile),
            );
        }

        $dependencies = [];
        foreach ($rawDependencies as $dependencyName) {
            if (!is_string($dependencyName) || $dependencyName === '') {
                throw new InvalidArgumentException(
                    sprintf('Skill "%s" frontmatter "depends_on:" contains a non-string entry.', $skillFile),
                );
            }

            $dependencies[] = SkillNameVo::createFromName($dependencyName);
        }

        return $dependencies;
    }
}
