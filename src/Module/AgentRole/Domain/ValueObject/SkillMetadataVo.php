<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Метаданные skill (навыка): результат разбора frontmatter файла SKILL.md.
 *
 * Содержит поля, необходимые для построения каталога skills в system prompt
 * (формат Agent Skills / pi): name, description, location (абсолютный путь к
 * SKILL.md) и список зависимостей depends_on для транзитивной развёртки.
 */
final readonly class SkillMetadataVo
{
    private readonly string $description;

    private readonly string $location;

    /**
     * @param SkillNameVo $name имя skill
     * @param string $description описание skill (что делает и когда применять); не пустое
     * @param string $location абсолютный путь к файлу SKILL.md; не пустой
     * @param list<SkillNameVo> $dependsOn имена skills, от которых зависит этот skill
     */
    public function __construct(
        private SkillNameVo $name,
        string $description,
        string $location,
        private array $dependsOn = [],
    ) {
        $description = trim($description);
        if ($description === '') {
            throw new InvalidArgumentException(sprintf('Skill "%s" description must not be empty.', $name->value()));
        }

        $location = trim($location);
        if ($location === '') {
            throw new InvalidArgumentException(sprintf('Skill "%s" location must not be empty.', $name->value()));
        }

        $this->description = $description;
        $this->location = $location;
    }

    public function getName(): SkillNameVo
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    /**
     * @return list<SkillNameVo>
     */
    public function getDependsOn(): array
    {
        return $this->dependsOn;
    }
}
