<?php

declare(strict_types=1);

return [
    // Files and directories to scan.
    'paths' => ['docs/', 'todo/', 'README.md', 'AGENTS.md'],

    // Path fragments to exclude (substring match).
    'exclude' => [
        'docs/todo-md/templates/',
    ],
];
