<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Метаданные роли агента: результат разбора frontmatter файла роли.
 *
 * Содержит имя роли, абсолютный путь к файлу роли и декларированный в
 * frontmatter список skills (поле `skills:`). Зависимости skills (`depends_on`)
 * разворачиваются отдельно — на этапе резолвинга.
 */
final readonly class RoleMetadataVo
{
    private readonly string $filePath;

    /**
     * @param RoleNameVo $name имя роли
     * @param string $filePath абсолютный путь к файлу роли; не пустой
     * @param list<SkillNameVo> $skills skills роли из frontmatter
     */
    public function __construct(
        private RoleNameVo $name,
        string $filePath,
        private array $skills,
    ) {
        $filePath = trim($filePath);
        if ($filePath === '') {
            throw new InvalidArgumentException(sprintf('Role "%s" file path must not be empty.', $name->value()));
        }

        $this->filePath = $filePath;
    }

    public function getName(): RoleNameVo
    {
        return $this->name;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @return list<SkillNameVo>
     */
    public function getSkills(): array
    {
        return $this->skills;
    }
}
