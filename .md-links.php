<?php

declare(strict_types=1);

return [
    // Files and directories to scan.
    'paths' => ['docs/', 'todo/', 'README.md', 'AGENTS.md'],

    // Path fragments to exclude (substring match).
    // `docs/git-workflow/` и `docs/todo-md/` — локально устанавливаемые копии доков
    // пакетов prikotov/git-workflow и prikotov/todo-md (см. docs/.gitignore):
    // в репозиторий не коммитятся, их внутренние ссылки проверяются в самих пакетах.
    'exclude' => [
        'docs/git-workflow/',
        'docs/todo-md/',
        'todo/done/',
        'todo/cancelled/',
        'todo/backlog/',
    ],
];
