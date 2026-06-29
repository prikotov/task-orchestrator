<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop;

use Override;
use TaskOrchestrator\Common\Component\ModuleSystem\ModuleInterface;

/**
 * Модуль DynamicLoop.
 *
 * Регистрируется в config/modules.php; Kernel через ModuleKernelTrait
 * подгружает Resource/config/services.yaml с параметрами module.dynamic_loop.*
 * и DI-конфигурацией динамических циклов.
 */
final class DynamicLoopModule implements ModuleInterface
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
        return 'TaskOrchestrator\\Common\\Module\\DynamicLoop';
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getServiceExcludePaths(): array
    {
        return [...self::DEFAULT_SERVICE_EXCLUDE_PATHS, 'DynamicLoopModule.php'];
    }
}
