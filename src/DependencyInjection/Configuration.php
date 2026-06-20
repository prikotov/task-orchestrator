<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\DependencyInjection;

use Override;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Конфигурация TaskOrchestrator.
 *
 * Определяет схему параметров:
 * - roles_dir: путь к .md файлам ролей
 * - chains_yaml: путь к YAML-конфигурации цепочек
 * - chains_session_dir: путь к каталогу сессий оркестрации
 * - base_path: корень проекта для path relativization
 * - git_identity: опциональная секция модуля GitIdentity (GitHub App installation token).
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
                // ── GitIdentity module (optional) ──────────────────────────────
                ->arrayNode('git_identity')
                    ->addDefaultsIfNotSet()
                    ->info('GitHub App installation token module (enabled via app_id + private key).')
                    ->children()
                        ->scalarNode('app_id')
                            ->defaultNull()
                            ->info('GitHub App ID. Required when command/use case is used.')
                        ->end()
                        ->scalarNode('private_key_path')
                            ->defaultNull()
                            ->info('Path to PEM private key file (chmod 0600). Preferred key source.')
                        ->end()
                        ->scalarNode('private_key')
                            ->defaultNull()
                            ->info('Inline PEM private key content (env-provided). Alternative key source.')
                        ->end()
                        ->scalarNode('api_base_uri')
                            ->defaultValue('https://api.github.com')
                            ->info('GitHub API base URI (overridable for GitHub Enterprise).')
                        ->end()
                        ->scalarNode('github_api_version')
                            ->defaultValue('2026-03-10')
                            ->info('X-GitHub-Api-Version header value (overridable for GHES).')
                        ->end()
                        ->scalarNode('user_agent')
                            ->defaultValue('task-orchestrator-git-identity')
                            ->info('HTTP User-Agent (required by GitHub best practice).')
                        ->end()
                        ->scalarNode('cache_dir')
                            ->defaultValue('%task_orchestrator.base_path%/var/cache/task-orchestrator/git-identity')
                            ->info('Filesystem cache directory (0700).')
                        ->end()
                        ->integerNode('jwt_ttl_seconds')
                            ->defaultValue(540)
                            ->min(1)
                            ->max(600)
                            ->info('JWT lifetime in seconds (GitHub allows max 600).')
                        ->end()
                        ->integerNode('jwt_clock_skew_seconds')
                            ->defaultValue(60)
                            ->min(0)
                            ->info('Seconds to backdate iat (NTP drift tolerance).')
                        ->end()
                        ->integerNode('token_expiry_safety_margin_seconds')
                            ->defaultValue(60)
                            ->min(0)
                            ->info('Safety margin subtracted from token expiry for cache TTL.')
                        ->end()
                        ->integerNode('installation_id_cache_ttl_seconds')
                            ->defaultValue(86400)
                            ->min(0)
                            ->info('TTL of installation id cache. Use treatNullLike for no-expiry.')
                        ->end()
                        ->booleanNode('scope_to_repository')
                            ->defaultTrue()
                            ->info('Scope installation token to the requested repository.')
                        ->end()
                        ->integerNode('request_timeout_seconds')
                            ->defaultValue(30)
                            ->min(1)
                            ->info('HTTP stream context timeout.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
