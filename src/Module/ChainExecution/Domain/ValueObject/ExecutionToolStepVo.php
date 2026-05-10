<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Execution VO: описание tool-шага для выполнения.
 *
 * Маппится из ChainDefinition\Domain\ValueObject\ToolStepVo через Integration-маппер.
 */
final readonly class ExecutionToolStepVo
{
    public function __construct(
        public string $command,
        public string $label,
        public int $timeoutSeconds = 120,
        public ?string $outputKey = null,
    ) {
        if (trim($command) === '') {
            throw new InvalidArgumentException('ExecutionToolStepVo::command must not be empty.');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException('ExecutionToolStepVo::label must not be empty.');
        }
    }
}
