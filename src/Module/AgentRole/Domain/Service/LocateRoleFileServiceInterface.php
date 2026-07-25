<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Service;

use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\RoleFileNotFoundException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleNameVo;

/**
 * Locates (находит) файл роли по её имени.
 *
 * Инфраструктурный контракт: реализация выполняет поиск в каталоге ролей
 * (`task_orchestrator.roles_dir`) с учётом локали приложения (env APP_LOCALE).
 * Приоритет: `<role>.<locale>.md` → `<role>.md` (локаль-нейтральный) →
 * glob `<role>.*.md` (первый найденный перевод).
 */
interface LocateRoleFileServiceInterface
{
    /**
     * @return string абсолютный путь к файлу роли
     *
     * @throws RoleFileNotFoundException если файл роли не найден
     */
    public function locate(RoleNameVo $roleName): string;
}
