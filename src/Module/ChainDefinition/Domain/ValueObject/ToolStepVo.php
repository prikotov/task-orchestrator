<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object описания tool-шага для цепочки оркестрации.
 *
 * Tool-шаг — детерминированная shell-команда, stdout которой
 * записывается в ChainContext по ключу outputKey.
 */
final readonly class ToolStepVo
{
    /**
     * @param string $command shell-команда для выполнения (например: 'git rev-parse HEAD')
     * @param string $label человекочитаемое название (например: 'Get current commit')
     * @param int $timeoutSeconds таймаут выполнения в секундах (default: 120)
     * @param string|null $outputKey ключ в ChainContext для записи stdout (null = не записывать)
     */
    public function __construct(
        public string $command,
        public string $label,
        public int $timeoutSeconds = 120,
        public ?string $outputKey = null,
    ) {
        if (trim($command) === '') {
            throw new InvalidArgumentException('ToolStepVo::command must not be empty.');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException('ToolStepVo::label must not be empty.');
        }

        if ($outputKey !== null && trim($outputKey) === '') {
            throw new InvalidArgumentException('ToolStepVo::outputKey must not be empty string.');
        }
    }
}
