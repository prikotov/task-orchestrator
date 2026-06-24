<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner;

use Override;
use TaskOrchestrator\Common\Component\ModuleSystem\ModuleInterface;

/**
 * Модуль AgentRunner.
 *
 * Регистрируется в config/modules.php; Kernel через ModuleKernelTrait
 * подгружает Resource/config/services.yaml с параметрами module.agent_runner.*
 * и DI-конфигурацией runner'ов агентов.
 */
final class AgentRunnerModule implements ModuleInterface
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
