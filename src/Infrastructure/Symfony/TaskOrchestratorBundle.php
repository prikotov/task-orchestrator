<?php

declare(strict_types=1);

namespace TaskOrchestrator\Infrastructure\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use TaskOrchestrator\DependencyInjection\TaskOrchestratorExtension;

/**
 * Symfony Bundle для TaskOrchestrator.
 *
 * Загружает config/services.yaml и обрабатывает параметры конфигурации
 * (roles_dir, chains_yaml, audit_log_path, chains_session_dir, base_path).
 *
 * Extension расположен в TaskOrchestrator\DependencyInjection\,
 * а Bundle — в TaskOrchestrator\Infrastructure\Symfony\.
 * Метод getContainerExtensionClass() переопределён для корректного разрешения.
 */
class TaskOrchestratorBundle extends Bundle
{
    #[\Override]
    protected function getContainerExtensionClass(): string
    {
        return TaskOrchestratorExtension::class;
    }
}
