<?php

declare(strict_types=1);

// Конфигурация prikotov/coding-standard для проекта task-orchestrator.
// docs_path резолвится относительно расположения этого файла (корень проекта).
return [
    'docs_path' => 'docs/conventions',

    // validate-language: доля англицизмов в русскоязычной документации.
    'language' => [
        // Пути для сканирования (todo/ исключён — рабочие постановки с шаблонными англ. заголовками секций).
        'paths' => ['docs/', 'README.md', 'AGENTS.md'],

        // Фрагменты путей для исключения (англоязычные справочники, research с английскими цитатами).
        'exclude' => [
            'docs/research',          // research-отчёты содержат английские названия продуктов/цитаты
            'docs/agents/reports',    // agent-reports с техническими английскими выводами
            'README.en.md',           // англоязычный README
            'docs/conventions',      // внешний — из prikotov/coding-standard (через coding-standard-init)
            'docs/todo-md',          // внешний — из prikotov/todo-md
            'docs/git-workflow',     // внешний — из prikotov/git-workflow
        ],

        // Максимально допустимая доля англицизмов (0.08 = 8%).
        // Этап внедрения: warning mode, не strict — пока чистится baseline.
        'max_ratio' => 0.08,

        // Разрешённые термины (case-insensitive) — легитимный жаргон и методология.
        // Граница: общие английские слова нужно переводить, а не добавлять в allowlist.
        'allowlist' => [
            // Технологии, стандарты и контракты, используемые проектом.
            'Symfony', 'PHP', 'SQL', 'API', 'REST', 'DTO', 'DDD',
            'CLI', 'JSON', 'JSONL', 'YAML', 'HTTP', 'HTTPS',
            'URL', 'URI', 'UUID', 'PSR', 'MCP', 'SDK', 'ORM', 'SOLID',
            'SRP', 'DI', 'ACL', 'KISS', 'YAGNI', 'DRY',

            // Термины конвенций и имена компонентов DDD.
            'VO', 'Value Object', 'Value Objects', 'Clean Architecture', 'Shared Kernel',
            'Command Handler', 'Query Handler', 'Event Listener', 'CriteriaMapper',
            'Circuit Breaker', 'Quality Gate', 'CQRS', 'GRASP', 'RACI',
            'Entity', 'Repository',
            'UseCase', 'Application', 'Domain', 'Infrastructure', 'Presentation',
            'Integration', 'Module', 'Bundle', 'Component', 'Helper', 'Factory',
            'Mapper', 'Service', 'Decorator',

            // Установленные инструменты и интеграции.
            'PHPUnit', 'Psalm', 'Deptrac', 'PHPCS', 'PHP_CodeSniffer', 'Composer',
            'GitHub', 'Codex', 'Pi', 'Qwen', 'Gemini', 'OpenCode', 'Kilo', 'Twig',
            'PHPDoc',

            // Имена классов и ключей конфигурации task-orchestrator.
            'fix_iterations', 'max_iterations', 'CircuitBreaker', 'CommandHandler',
            'ChainDefinition', 'ChainExecution', 'AgentRunner', 'DynamicLoop',
            'ExecutionStrategy', 'ChainStep', 'ChainSecurityPolicy', 'Orchestrator',
            'DynamicExecutionStrategy', 'StaticExecutionStrategy', 'ConditionalExecutionStrategy',
            'StaticExecution',
            'AgentDtoMapper', 'AgentRunnerInterface', 'BuildDynamicContextService',
            'ChainDefinitionVo', 'ChainRetryPolicyVo', 'ChainSessionLogger',
            'CheckpointWriter', 'DynamicLoopExecution', 'ExecutionStrategyInterface',
            'GitHubHttpComponent', 'GitHubHttpException', 'HttpsProxyBridge',
            'RunAgentService', 'RunDynamicLoopService', 'RunStaticChainService',
            'SharedChainDefinitionVo', 'StaticChainExecution',

            // Идентичность проекта и модель личности ролей.
            'task-orchestrator', 'Task Orchestrator', 'prikotov', 'GLM', 'jung', 'belbin', 'disc', 'dp',
            // Психометрическая модель.
            'Big Five',

            // Идентификаторы форматов, skills и CLI-команд проекта.
            'allowlist', 'env', 'XML', 'SKILL', 'ADR', 'OQ', 'TASK', 'YAML DSL',
            'agent:init', 'agent:orchestrate', 'agent:role-skills', 'agent:run',
            'agent:runners', 'agent:token',
        ],
    ],
];
