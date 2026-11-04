<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Task;

interface TaskComplexityResolverInterface
{
    public function resolve(string $task): int;
}
