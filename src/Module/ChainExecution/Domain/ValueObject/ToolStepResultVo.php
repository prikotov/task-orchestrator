<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Value Object результата выполнения tool-шага.
 *
 * Содержит exit code, stdout и флаг успешности выполнения.
 */
final readonly class ToolStepResultVo
{
    /**
     * @param int $exitCode код завершения процесса
     * @param string $stdout стандартный вывод команды
     * @param bool $success признак успешности (exit code === 0)
     * @param float $durationMs длительность выполнения в миллисекундах
     */
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public bool $success,
        public float $durationMs,
    ) {
    }
}
