<?php

declare(strict_types=1);

// Lists canonical roles and agents for author/assignee validation.
// Full reference: docs/todo-md/reference/CONFIG.md
return [
    // Канонические роли проекта (текст перед скобками) — функциональные
    // обязанности команды. Персоны ролей (Алекс, Шерлок, Гермиона, ...)
    // описаны в docs/agents/roles/team/ и в author/assignee не кодируются.
    // Пусто/отсутствует — роль проверяется только по формату; проект ведёт
    // явный список ролей.
    'roles' => [
        'Тимлид',
        'Аналитик',
        'Архитектор',
        'Бэкендер',
        'Ревьювер Бэка',
        'Тестировщик Бэка',
        'Технический писатель',
    ],

    // Канонические агенты (lowercase-идентификатор в скобках) — AI-харнесы,
    // которыми выполняется работа. Пакетный список из reference/AI_AGENTS.md
    // расширен агентом `omp`, который используется в этом проекте.
    'agents' => ['pi', 'codex', 'codex-cli', 'omp', 'opencode'],

    // Считать нарушения author/assignee ошибками (аналог флага --strict).
    'strict' => false,
];
