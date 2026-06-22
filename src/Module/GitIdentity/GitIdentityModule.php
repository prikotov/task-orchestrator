<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity;

use Override;
use TaskOrchestrator\Common\Component\ModuleSystem\ModuleInterface;

/**
 * Модуль GitIdentity.
 *
 * Регистрируется в config/modules.php; Kernel (через ModuleKernelTrait)
 * регистрирует ModuleCompilerPass, который подгружает Resource/config/services.yaml
 * с параметрами module.git_identity.* и привязками интерфейсов к реализациям.
 */
final class GitIdentityModule implements ModuleInterface
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
}
