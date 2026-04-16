<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\ValueObject;

/**
 * Value Object результата выполнения quality gate.
 *
 * Содержит статус (пройден/не пройден), exit code, вывод команды и длительность.
 */
final readonly class QualityGateResultVo
{
    /**
     * @param string $label название gate
     * @param bool $passed пройден ли gate (exit code === 0)
     * @param int $exitCode код завершения процесса
     * @param string $output stdout + stderr процесса
     * @param float $durationMs длительность выполнения в миллисекундах
     */
    public function __construct(
        public string $label,
        public bool $passed,
        public int $exitCode,
        public string $output,
        public float $durationMs,
    ) {
    }
}
