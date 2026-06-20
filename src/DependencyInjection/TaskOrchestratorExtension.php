<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\DependencyInjection;

use Override;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Extension для TaskOrchestrator.
 *
 * Загружает config/services.yaml и регистрирует параметры конфигурации
 * (roles_dir, chains_yaml, chains_session_dir, base_path, package_dir).
 *
 * Используется напрямую в CLI entry point (bin/task-orchestrator)
 * без Symfony Kernel.
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
        $packageDir = dirname(__DIR__, 2);

        $container->setParameter('task_orchestrator.roles_dir', $config['roles_dir']);
        $container->setParameter('task_orchestrator.chains_yaml', $config['chains_yaml']);
        $container->setParameter('task_orchestrator.chains_session_dir', $config['chains_session_dir']);
        $container->setParameter('task_orchestrator.base_path', $config['base_path']);
        $container->setParameter('task_orchestrator.package_dir', $packageDir);

        $this->registerGitIdentityParameters($container, $config);

        $loader = new YamlFileLoader($container, new FileLocator($packageDir . '/config'));
        $loader->load('services.yaml');
    }

    /**
     * Регистрирует параметры модуля GitIdentity в контейнере из секции
     * `task_orchestrator.git_identity` (дефолты задаются в {@see Configuration}).
     *
     * @param array<array-key, mixed> $config
     */
    private function registerGitIdentityParameters(ContainerBuilder $container, array $config): void
    {
        /** @var array<string, mixed> $git */
        $git = $config['git_identity'] ?? [];

        $map = [
            'app_id' => 'app_id',
            'private_key_path' => 'private_key_path',
            'private_key' => 'private_key',
            'api_base_uri' => 'api_base_uri',
            'github_api_version' => 'github_api_version',
            'user_agent' => 'user_agent',
            'cache_dir' => 'cache_dir',
            'jwt_ttl_seconds' => 'jwt_ttl_seconds',
            'jwt_clock_skew_seconds' => 'jwt_clock_skew_seconds',
            'token_expiry_safety_margin_seconds' => 'token_expiry_safety_margin_seconds',
            'installation_id_cache_ttl_seconds' => 'installation_id_cache_ttl_seconds',
            'scope_to_repository' => 'scope_to_repository',
            'request_timeout_seconds' => 'request_timeout_seconds',
        ];

        foreach ($map as $configKey => $paramKey) {
            if (array_key_exists($configKey, $git)) {
                $container->setParameter('task_orchestrator.git_identity.' . $paramKey, $git[$configKey]);
            }
        }
    }
}
