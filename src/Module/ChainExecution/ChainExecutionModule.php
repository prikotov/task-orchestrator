<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution;

use Override;
use TaskOrchestrator\Common\Component\ModuleSystem\ModuleInterface;

/**
 * Модуль ChainExecution.
 *
 * Регистрируется в config/modules.php; Kernel через ModuleKernelTrait
 * подгружает Resource/config/services.yaml с параметрами module.chain_execution.*
 * и DI-конфигурацией исполнения цепочек.
 */
final class ChainExecutionModule implements ModuleInterface
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
