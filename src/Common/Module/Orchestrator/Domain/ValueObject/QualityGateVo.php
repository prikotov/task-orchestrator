<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object описания quality gate для шага цепочки.
 *
 * Quality gate — произвольная shell-команда, выполняемая после шага агента.
 * Если команда возвращает ненулевой exit code — gate считается не пройденным.
 */
final readonly class QualityGateVo
{
    /**
     * @param string $command shell-команда для выполнения (например: 'make tests-unit')
     * @param string $label человекочитаемое название (например: 'Unit Tests')
     * @param int $timeoutSeconds таймаут выполнения в секундах
     */
    public function __construct(
        public string $command,
        public string $label,
        public int $timeoutSeconds = 120,
    ) {
        if (trim($command) === '') {
            throw new InvalidArgumentException('QualityGateVo::command must not be empty.');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException('QualityGateVo::label must not be empty.');
        }
    }
}
