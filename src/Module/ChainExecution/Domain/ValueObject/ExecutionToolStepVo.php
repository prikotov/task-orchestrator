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
        private string $command,
        private string $label,
        private int $timeoutSeconds = 120,
        private ?string $outputKey = null,
    ) {
        if (trim($command) === '') {
            throw new InvalidArgumentException('ExecutionToolStepVo::command must not be empty.');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException('ExecutionToolStepVo::label must not be empty.');
        }
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function getOutputKey(): ?string
    {
        return $this->outputKey;
    }
}
