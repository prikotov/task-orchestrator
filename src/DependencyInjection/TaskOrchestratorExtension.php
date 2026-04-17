<?php

declare(strict_types=1);

namespace TaskOrchestrator\DependencyInjection;

use Override;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Extension для TaskOrchestratorBundle.
 *
 * Загружает config/services.yaml и регистрирует параметры конфигурации
 * (roles_dir, chains_yaml, audit_log_path, chains_session_dir, base_path).
 */
class TaskOrchestratorExtension extends Extension
{
    /**
     * @param array<array-key, array<array-key, mixed>> $configs
     *
     * @throws \Exception
     */
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('task_orchestrator.roles_dir', $config['roles_dir']);
        $container->setParameter('task_orchestrator.chains_yaml', $config['chains_yaml']);
        $container->setParameter('task_orchestrator.audit_log_path', $config['audit_log_path']);
        $container->setParameter('task_orchestrator.chains_session_dir', $config['chains_session_dir']);
        $container->setParameter('task_orchestrator.base_path', $config['base_path']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');
    }
}
