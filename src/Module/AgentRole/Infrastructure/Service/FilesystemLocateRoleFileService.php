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
 * Учитывает локаль файла: предпочтение отдаётся `<role>.ru.md` (основная локаль
 * проекта), затем `<role>.md`, затем любой `<role>.<locale>.md` (первый найденный).
 */
final readonly class FilesystemLocateRoleFileService implements LocateRoleFileServiceInterface
{
    public function __construct(
        private string $rolesDir,
    ) {
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
        $explicit = [
            $this->rolesDir . '/' . $roleName . '.ru.md',
            $this->rolesDir . '/' . $roleName . '.md',
        ];

        $globbed = glob($this->rolesDir . '/' . $roleName . '.*.md');

        return array_merge($explicit, $globbed !== false ? $globbed : []);
    }
}
