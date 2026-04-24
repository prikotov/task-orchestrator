<?php

declare(strict_types=1);

/**
 * Creates vendor/bin/task-orchestrator symlink for local development.
 *
 * Composer only installs vendor binaries for dependencies, not for the root package.
 * This script bridges the gap so `vendor/bin/task-orchestrator` works locally.
 * When installed as a dependency, Composer handles this automatically.
 */

$binDir = __DIR__ . '/../vendor/bin';
$target = '../../bin/task-orchestrator';
$link = $binDir . '/task-orchestrator';

if (!is_dir($binDir)) {
    return;
}

if (file_exists($link) || is_link($link)) {
    return;
}

symlink($target, $link);
