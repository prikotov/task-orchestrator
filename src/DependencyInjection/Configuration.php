<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\DependencyInjection;

use Override;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Конфигурация TaskOrchestratorBundle.
 *
 * Определяет схему параметров bundle:
 * - roles_dir: путь к .md файлам ролей
 * - chains_yaml: путь к YAML-конфигурации цепочек
 * - chains_session_dir: путь к каталогу сессий оркестрации
 * - base_path: корень проекта для path relativization
 */
class Configuration implements ConfigurationInterface
{
    #[Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('task_orchestrator');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('roles_dir')
                    ->isRequired()
                    ->info('Path to role prompt .md files (e.g. %%kernel.project_dir%%/docs/agents/roles/team)')
                ->end()
                ->scalarNode('chains_yaml')
                    ->isRequired()
                    ->info('Path to chains YAML configuration (e.g. %%kernel.project_dir%%/apps/console/config/agent_chains.yaml)')
                ->end()
                ->scalarNode('chains_session_dir')
                    ->isRequired()
                    ->info('Path to chains session directory (e.g. %%kernel.project_dir%%/var/agent/chains)')
                ->end()
                ->scalarNode('base_path')
                    ->isRequired()
                    ->info('Project root for path relativization (e.g. %%kernel.project_dir%%)')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
