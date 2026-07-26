<?php

declare(strict_types=1);

// Конфигурация prikotov/coding-standard для проекта task-orchestrator.
// docs_path резолвится относительно расположения этого файла (корень проекта).
return [
    'docs_path' => 'docs/conventions',

    // validate-language: доля англицизмов в русскоязычной документации/задачах.
    'language' => [
        // Пути для сканирования.
        'paths' => ['docs/', 'todo/', 'README.md', 'AGENTS.md'],

        // Фрагменты путей для исключения (англоязычные справочники, research с английскими цитатами).
        'exclude' => [
            'docs/research',          // research-отчёты содержат английские названия продуктов/цитаты
            'docs/agents/reports',    // agent-reports с техническими английскими выводами
            'README.en.md',           // англоязычный README
        ],

        // Максимально допустимая доля англицизмов (0.02 = 2%).
        // Этап внедрения: warning mode, не strict — пока чистится baseline.
        'max_ratio' => 0.02,

        // Разрешённые термины (case-insensitive) — технический жаргон проекта.
        'allowlist' => [
            // Технологии и стандарты
            'Symfony', 'Doctrine', 'PHP', 'SQL', 'MySQL', 'PostgreSQL', 'SQLite',
            'API', 'REST', 'GraphQL', 'DTO', 'DDD', 'CLI', 'JSON', 'JSONL', 'YAML',
            'HTML', 'CSS', 'HTTP', 'HTTPS', 'URL', 'URI', 'UUID', 'PSR', 'MIT',
            'MCP', 'SDK', 'ORM', 'DBAL', 'SOLID', 'SRP', 'DI', 'IoC', 'ACL',
            'TDD', 'BDD', 'MoSCoW', 'SMART', 'RACI', 'KISS', 'YAGNI', 'DRY',
            'Vo', 'Entity', 'Repository', 'UseCase', 'Application', 'Domain',
            'Infrastructure', 'Presentation', 'Integration', 'Module', 'Bundle',
            'Component', 'Helper', 'Factory', 'Mapper', 'Service', 'Decorator',
            'CQRS', 'ES', 'EDA',

            // Инструменты
            'PHPUnit', 'Psalm', 'PHPStan', 'Deptrac', 'PHPMD', 'PHPCS',
            'PHP_CodeSniffer', 'Composer', 'Packagist', 'GitHub', 'GitLab',
            'Git', 'Codex', 'Bun', 'Node', 'Node.js', 'npm', 'JavaScript',
            'TypeScript', 'Python', 'Rust', 'Go', 'Clojure', 'Babashka',
            'Linux', 'Docker', 'Podman', 'Electron', 'React', 'Ghostty',
            'Warp', 'tmux', 'Vercel', 'Effect-TS',

            // Git / процесс
            'PR', 'merge', 'commit', 'push', 'branch', 'checkout', 'rebase',
            'tag', 'release', 'issue', 'epic', 'task', 'todo', 'backlog',
            'sprint', 'milestone', 'label', 'review', 'approval', 'status',
            'draft', 'pipeline', 'workflow', 'hotfix', 'cherry-pick', 'squash',
            'fork', 'clone', 'origin', 'upstream', 'HEAD', 'main', 'dev', 'master',

            // Архитектура оркестрации (жаргон проекта)
            'chain', 'runner', 'retry', 'fallback', 'payload', 'audit', 'loop',
            'agent', 'subagent', 'provider', 'model', 'skill', 'role', 'token',
            'budget', 'gate', 'breaker', 'circuit', 'quality', 'iteration',
            'snapshot', 'baseline', 'scope', 'checkpoint', 'liveness', 'stall',
            'backoff', 'timeout', 'handoff', 'fan-out', 'fanout', 'dispatch',
            'orchestrator', 'orchestration', 'harness', 'runtime', 'spawn',
            'turn', 'context', 'compaction', 'doom', 'intent', 'discipline',
            'fix_iterations', 'max_iterations', 'CircuitBreaker',

            // Продукт/идентичность
            'TasK', 'task-orchestrator', 'OhMyPi', 'omp', 'Pi', 'Codex',
            'Claude', 'ClaudeCode', 'Gemini', 'Copilot', 'OpenCode', 'OpenClaw',
            'Orca', 'SwarmForge', 'OmO', 'AgentCraft', 'prikotov', 'GLM',

            // Прочий технический жаргон
            'allowlist', 'denylist', 'blocklist', 'backoffice', 'frontend',
            'backend', 'fullstack', 'oneline', 'multiline', 'standalone',
            'plugin', 'extension', 'addon', 'feature', 'fixture', 'stub',
            'mock', 'spy', 'fake', 'dummy', 'bootstrap', 'seed', 'migration',
        ],
    ],
];
