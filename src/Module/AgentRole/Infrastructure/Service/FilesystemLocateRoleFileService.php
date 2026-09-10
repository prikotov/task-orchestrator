<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service;

use Override;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\RoleFileNotFoundException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LocateRoleFileServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleNameVo;

/**
 * Поиск файла роли в каталоге ролей (roles_dir) по имени роли.
 *
 * При заданной локали предпочтение отдаётся `<role>.<locale>.md`, затем
 * `<role>.md`, затем любому `<role>.*.md`. Без настроенной локали порядок:
 * `<role>.md` → `<role>.en.md` → `<role>.ru.md` → `<role>.zh.md` → любой
 * `<role>.*.md`.
 */
final readonly class FilesystemLocateRoleFileService implements LocateRoleFileServiceInterface
{
    private string $locale;

    public function __construct(
        private string $rolesDir,
        string $locale,
    ) {
        $this->locale = strtolower($locale);
    }

    #[Override]
    public function locate(RoleNameVo $roleName): string
    {
        $name = $roleName->value();
        $candidates = $this->candidates($name);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $realPath = realpath($candidate);

                return $realPath !== false ? $realPath : $candidate;
            }
        }

        throw new RoleFileNotFoundException($name, sprintf('%s/%s.*.md', $this->rolesDir, $name));
    }

    /**
     * @return list<string>
     */
    private function candidates(string $roleName): array
    {
        $basePath = $this->rolesDir . '/' . $roleName;
        $globbed = glob($basePath . '.*.md');
        $translations = $globbed !== false ? $globbed : [];

        if ($this->locale !== '') {
            return array_values(array_unique(array_merge(
                [$basePath . '.' . $this->locale . '.md', $basePath . '.md'],
                $translations,
            )));
        }

        return array_values(array_unique(array_merge(
            [
                $basePath . '.md',
                $basePath . '.en.md',
                $basePath . '.ru.md',
                $basePath . '.zh.md',
            ],
            $translations,
        )));
    }
}
