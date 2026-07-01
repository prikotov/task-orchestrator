<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole;

use Override;
use TaskOrchestrator\Common\Component\ModuleSystem\ModuleInterface;

/**
 * Модуль AgentRole.
 *
 * Владеет доменной областью «роль агента и её skills»: резолвинг skills роли с
 * развёрткой зависимостей и форматирование каталога skills для system prompt
 * агента (формат Agent Skills / pi).
 *
 * Регистрируется в config/modules.php; Kernel (через ModuleKernelTrait)
 * регистрирует ModuleCompilerPass, который подгружает Resource/config/services.yaml
 * с параметрами module.agent_role.* и привязками интерфейсов к реализациям.
 */
final class AgentRoleModule implements ModuleInterface
{
    #[Override]
    public function getModuleDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getModuleConfigPath(): string
    {
        return $this->getModuleDir() . '/Resource/config';
    }

    #[Override]
    public function getServiceNamespace(): string
    {
        return 'TaskOrchestrator\\Common\\Module\\AgentRole';
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getServiceExcludePaths(): array
    {
        return [...self::DEFAULT_SERVICE_EXCLUDE_PATHS, 'AgentRoleModule.php'];
    }
}
