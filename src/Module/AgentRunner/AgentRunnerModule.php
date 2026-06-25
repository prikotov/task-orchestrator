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

    #[Override]
    public function getServiceNamespace(): string
    {
        return 'TaskOrchestrator\\Common\\Module\\AgentRunner';
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getServiceExcludePaths(): array
    {
        // Module-specific excludes помимо стандартного DDD-набора:
        //  - Resources/ — каталог с runtime-ресурсами runner'ов;
        //  - декораторы runner'ов (Retrying/CircuitBreaker) — это не
        //    самостоятельные сервисы, а декораторы PiAgentRunnerService,
        //    собираемые явно фабрикой RetryableRunnerFactory.
        return [
            ...self::DEFAULT_SERVICE_EXCLUDE_PATHS,
            'AgentRunnerModule.php',
            'Resources/',
            'Infrastructure/Service/RetryingAgentRunnerService.php',
            'Infrastructure/Service/CircuitBreakerAgentRunnerService.php',
        ];
    }
}
