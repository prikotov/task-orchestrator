<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\Task;

use InvalidArgumentException;
use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Task\TaskComplexityResolverInterface;

final readonly class FileTaskComplexityResolverService implements TaskComplexityResolverInterface
{
    public function __construct(private string $basePath)
    {
    }

    #[Override]
    public function resolve(string $task): int
    {
        if (preg_match('/TASK-[a-z0-9-]+/i', $task, $matches) !== 1) {
            throw new InvalidArgumentException('Task context must contain a TASK-* identifier.');
        }

        $taskId = $matches[0];
        $paths = glob(rtrim($this->basePath, '/') . '/todo/{,done/,backlog/,cancelled/}' . $taskId . '.todo.md', GLOB_BRACE);
        if ($paths === false) {
            $paths = [];
        }
        if (count($paths) !== 1) {
            throw new InvalidArgumentException(sprintf('Expected exactly one file for task "%s", found %d.', $taskId, count($paths)));
        }

        $content = file_get_contents($paths[0]);
        if ($content === false) {
            throw new InvalidArgumentException(sprintf('Unable to read task file "%s".', $paths[0]));
        }
        if (preg_match('/^complexity:\s*(C[0-5])\s*$/mi', $content, $matches) !== 1) {
            if (preg_match('/^complexity:\s*(\S+)\s*$/mi', $content, $matches) === 1) {
                throw new InvalidArgumentException(sprintf('Invalid complexity "%s" in task "%s".', $matches[1], $taskId));
            }

            return 3;
        }

        return (int) substr($matches[1], 1);
    }
}
