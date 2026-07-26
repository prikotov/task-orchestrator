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
        'allowlist' => [
            // Методология и шаблон todo/epic (двуязычные заголовки секций)
            'Concept', 'Goal', 'Story', 'Job', 'Requirements', 'Must', 'Have',
            'Should', 'Could', "Won't", 'and', 'of', 'In', 'Out', 'Use', 'Case',
            'Solution', 'Sketch', 'Expected', 'Result', 'Human', 'Brief', 'Problem',
            'Definition', 'Done', 'Verification', 'Risks', 'Sources', 'Comments',
            'Implementation', 'Plan', 'Context', 'Change', 'History', 'Release',
            'Notes', 'Deployment', 'Dependencies', 'brainstorm', 'self-review',
            'code-review', 'retrospective', 'acceptance', 'criteria', 'story',

            // Технологии и стандарты
            'Symfony', 'Doctrine', 'PHP', 'SQL', 'MySQL', 'PostgreSQL', 'SQLite',
            'API', 'REST', 'GraphQL', 'DTO', 'DDD', 'CLI', 'JSON', 'JSONL', 'YAML',
            'HTML', 'CSS', 'HTTP', 'HTTPS', 'URL', 'URI', 'UUID', 'PSR', 'MIT',
            'MCP', 'SDK', 'ORM', 'DBAL', 'SOLID', 'SRP', 'DI', 'IoC', 'ACL',
            'TDD', 'BDD', 'MoSCoW', 'SMART', 'RACI', 'KISS', 'YAGNI', 'DRY',
            'Vo', 'Entity', 'Repository', 'UseCase', 'Application', 'Domain',
            'Infrastructure', 'Presentation', 'Integration', 'Module', 'Bundle',
            'Component', 'Helper', 'Factory', 'Mapper', 'Service', 'Decorator',
            'CQRS', 'ES', 'EDA', 'enum', 'namespace', 'value', 'object', 'class',

            // Инструменты
            'PHPUnit', 'Psalm', 'PHPStan', 'Deptrac', 'PHPMD', 'PHPCS',
            'PHP_CodeSniffer', 'Composer', 'Packagist', 'GitHub', 'GitLab',
            'Git', 'Codex', 'Bun', 'Node', 'Node.js', 'npm', 'JavaScript',
            'TypeScript', 'Python', 'Rust', 'Go', 'Clojure', 'Babashka',
            'Linux', 'Docker', 'Podman', 'Electron', 'React', 'Ghostty',
            'Warp', 'tmux', 'Vercel', 'Effect-TS', 'Twig', 'phpdoc', 'Phar',

            // Git / процесс
            'PR', 'merge', 'commit', 'push', 'branch', 'checkout', 'rebase',
            'tag', 'release', 'issue', 'epic', 'task', 'todo', 'backlog',
            'sprint', 'milestone', 'label', 'review', 'approval', 'status',
            'draft', 'pipeline', 'workflow', 'hotfix', 'cherry-pick', 'squash',
            'fork', 'clone', 'origin', 'upstream', 'HEAD', 'main', 'dev', 'master',
            'pull', 'exit', 'changelog', 'docs', 'research', 'roadmap', 'ADR',
            'loc', 'LOC',

            // Архитектура оркестрации (жаргон проекта)
            'chain', 'chains', 'runner', 'retry', 'fallback', 'payload', 'audit',
            'loop', 'agent', 'subagent', 'provider', 'model', 'skill', 'skills',
            'role', 'token', 'budget', 'gate', 'gates', 'breaker', 'circuit',
            'quality', 'iteration', 'snapshot', 'baseline', 'scope', 'checkpoint',
            'liveness', 'stall', 'backoff', 'timeout', 'handoff', 'fan-out',
            'fanout', 'dispatch', 'orchestrator', 'orchestration', 'harness',
            'runtime', 'spawn', 'turn', 'context', 'compaction', 'doom', 'intent',
            'discipline', 'fix_iterations', 'max_iterations', 'CircuitBreaker',
            'state', 'management', 'error', 'handling', 'handler', 'command',
            'commandhandler', 'CommandHandler', 'ChainDefinition', 'chaindefinition',
            'ChainExecution', 'chainexecution', 'AgentRunner', 'agentrunner',
            'DynamicLoop', 'dynamic', 'conditional', 'branching', 'dependency',
            'violations', 'violation', 'suppression', 'request', 'patch',
            'production', 'deploy', 'static', 'query', 'rule', 'rules', 'hooks',
            'prompt', 'resume', 'default', 'architecture', 'tests', 'test',
            'unit', 'source', 'stdout', 'smoke', 'security', 'voter', 'root',
            'front', 'web', 'ui', 'dsl', 'ci', 'ai', 'ai-agent', 'multi-agent',

            // Продукт/идентичность
            'TasK', 'task-orchestrator', 'OhMyPi', 'omp', 'Pi', 'Codex',
            'Claude', 'ClaudeCode', 'Gemini', 'Copilot', 'OpenCode', 'OpenClaw',
            'Orca', 'SwarmForge', 'OmO', 'AgentCraft', 'prikotov', 'GLM',
            'Archon', 'Mastra', 'Paperclip', 'Hermes', 'Zeroclaw', 'Sandcastle',
            'Duet', 'Multica', 'Kilo', 'ZCode',

            // Роли / психометрия (из файлов ролей)
            'jung', 'belbin', 'disc', 'dp', 'big', 'five', 'matter',

            // Прочий технический жаргон
            'allowlist', 'denylist', 'blocklist', 'backoffice', 'frontend',
            'backend', 'fullstack', 'oneline', 'multiline', 'standalone',
            'plugin', 'extension', 'addon', 'feature', 'fixture', 'stub',
            'mock', 'spy', 'fake', 'dummy', 'bootstrap', 'seed', 'migration',
            'coding', 'code', 'self-contained',

            // Дополнение из validate-language baseline
            'won', 'env', 'md', 'free', 'tier', 'programmatic', 'non-interactive',
            'ephemeral', 'byok', 'breaking', 'agent-frameworks-summary',
            'framework-comparisons', 'kernel', 'staticexecution', 'rfc',
            'readme', 'policy', 'permission', 'xml', 'specification',
            'in-memory', 'executionstrategy', 'execution', 'chaindefinitionvo',
            'system', 'console', 'split', 'open-source', 'source', 'shared',
            'cases', 'user', 'path', 'design', 'action', 'tools', 'time',
            'step', 'fix', 'changes', 'draft', 'ok', 'open', 'big',
        ],
    ],
];
