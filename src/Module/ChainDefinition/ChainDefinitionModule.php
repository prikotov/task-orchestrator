<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition;

use Override;
use TaskOrchestrator\Common\Component\ModuleSystem\ModuleInterface;

/**
 * Модуль ChainDefinition.
 *
 * Регистрируется в config/modules.php; Kernel через ModuleKernelTrait
 * подгружает Resource/config/services.yaml с параметрами module.chain_definition.*
 * и DI-конфигурацией модуля.
 */
final class ChainDefinitionModule implements ModuleInterface
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
