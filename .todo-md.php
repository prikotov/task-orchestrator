<?php

declare(strict_types=1);

// Project-level configuration for todo-md validate.
// Lists canonical roles and agents for author/assignee validation.
// Full reference: docs/todo-md/reference/CONFIG.md
return [
    // Канонические роли проекта (текст перед скобками) — персоны команды,
    // см. docs/agents/roles/team/.
    'roles' => [
        'Тимлид Алекс',
        'Аналитик Шерлок',
        'Архитектор Гэндальф',
        'Архитектор Локи',
        'Бэкендер Левша',
        'Бэкендер Тони',
        'Ревьювер Бэка Пуаро',
        'Тестировщик Бэка Хаус',
        'Технический писатель Гермиона',
        'Технический писатель Остап',
    ],

    // Канонические агенты (lowercase-идентификатор в скобках):
    // харнесы (pi, codex) + слаги персон команды.
    'agents' => [
        'pi', 'codex',
        'alex', 'sherlock', 'gandalf', 'loki',
        'levsha', 'tony', 'puaro', 'house', 'hermione', 'ostap',
    ],

    // Считать нарушения author/assignee ошибками (аналог флага --strict).
    'strict' => false,
];
