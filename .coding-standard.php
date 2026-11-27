<?php

declare(strict_types=1);

// Project-level configuration for prikotov/coding-standard.
// Required by the package's sniffs (ConfigRequiredSniff): `docs_path` tells
// sniff error messages where the conventions documentation was copied.
return [
    'docs_path' => 'docs/conventions',

    // Конфигурация метрик качества production-кода.
    // Корни исходников определяются из Composer autoload; пути ниже исключаются
    // даже при их попадании в область автозагрузки.
    'metrics' => [
        // Локальные входы анализаторов, снимок и HTML.
        'work_dir' => 'var/metrics',
        'deptrac_config' => 'depfile.yaml',
        'phpunit_config' => 'phpunit.xml.dist',
        // Метрики покрытия запускают только этот PHPUnit-suite, без интеграций.
        'phpunit_suite' => 'unit',
        'exclude' => [
            'vendor/', '.git/', 'var/', 'tmp/', 'packages/',
            'migrations/', 'config/', 'docs/',
            'public/', 'templates/', 'translations/',
        ],
        'module_patterns' => [
            'src/Module/*',
            'apps/*/src/Module/*',
            'apps/*/src/**/Module/*',
        ],
        // Допустимые границы project-level правил. Сборщик преобразует
        // превышения в findings; значения пересматриваются по распределениям.
        'thresholds' => [
            'method' => [
                'loc' => 50,
                'cc' => 10,
            ],
            'class' => [
                'loc' => 300,
                'wmc' => 50,
                'max_cc' => 10,
                'lcom4_components' => 1,
                'ce' => 10,
            ],
            'module' => [
                'external_dependency_share' => 0.5,
                'cycles' => 0,
            ],
        ],
    ],

    // Конфигурация validate-language (поиск англицизмов в русскоязычной документации).
    'language' => [
        // Пути для сканирования (todo/ исключён — рабочие постановки с шаблонными англ. заголовками секций).
        'paths' => ['docs/', 'README.md', 'AGENTS.md'],
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

            // Идентичность проекта и модель личности ролей.
            'task-orchestrator', 'Task Orchestrator', 'prikotov', 'GLM', 'jung', 'belbin', 'disc', 'dp',
            // Психометрическая модель.
            'Big Five',

            // Идентификаторы форматов и skills проекта.
            'allowlist', 'env', 'XML', 'SKILL', 'ADR', 'OQ', 'TASK', 'YAML DSL',
        ],
        // Стандартные переводы ЖАРГОНА/проектно-специфичных терминов с неоднозначным
        // переводом (НЕ обычных слов с очевидным переводом — тем словарь не нужен).
        // validate-language подсказывает перевод рядом со словом, на ratio не влияет.
        'dictionary' => [
            'hook' => 'хук',
            'god object' => 'божественный объект',
        ],
        // Фрагменты путей для исключения (англоязычные справочники, research с английскими цитатами).
        'exclude' => [
            'docs/research',          // research-отчёты содержат английские названия продуктов/цитаты
            'docs/agents/reports',    // agent-reports с техническими английскими выводами
            'README.en.md',           // англоязычный README
            'docs/conventions',       // внешний — из prikotov/coding-standard (через coding-standard-init)
            'docs/todo-md',           // внешний — из prikotov/todo-md
            'docs/git-workflow',      // внешний — из prikotov/git-workflow
        ],
    ],
];
