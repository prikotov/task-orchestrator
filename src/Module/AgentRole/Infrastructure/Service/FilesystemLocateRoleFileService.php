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
 * Учитывает локаль файла: предпочтение отдаётся `<role>.<locale>.md` (локаль
 * приложения из env APP_LOCALE), затем `<role>.md` (локаль-нейтральный), затем
 * любой `<role>.*.md` (первый найденный).
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
     * Кандидаты на файл роли в порядке приоритета:
     *   1) `<role>.<locale>.md`  — текущая локаль приложения;
     *   2) `<role>.md`           — локаль-нейтральный файл;
     *   3) glob `<role>.*.md`    — любой доступный перевод (первый найденный).
     *
     * @return list<string>
     */
    private function candidates(string $roleName): array
    {
        $explicit = [
            $this->rolesDir . '/' . $roleName . '.' . $this->locale . '.md',
            $this->rolesDir . '/' . $roleName . '.md',
        ];

        $globbed = glob($this->rolesDir . '/' . $roleName . '.*.md');

        return array_merge($explicit, $globbed !== false ? $globbed : []);
    }
}
