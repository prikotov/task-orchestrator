<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Service;

use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleMetadataVo;

/**
 * Читает метаданные роли из frontmatter файла роли.
 *
 * Инфраструктурный контракт: реализация парсит YAML-frontmatter файла роли и
 * извлекает декларацию skills (поле `skills:`).
 */
interface LoadRoleFrontmatterServiceInterface
{
    /**
     * @param string $filePath абсолютный путь к файлу роли
     */
    public function read(string $filePath): RoleMetadataVo;
}
