<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Dto;

/**
 * DTO шага цепочки оркестрации.
 *
 * Транспортный объект на границе Application ↔ Presentation.
 * Содержит только данные, необходимые Presentation-слою для отображения.
 */
final readonly class ChainStepDto
{
    /**
     * @param string $role роль агента (null для quality_gate)
     * @param string $runner имя runner'а
     * @param string $label человекочитаемое название (для quality_gate)
     * @param bool $isQualityGate является ли шаг quality gate
     * @param string|null $fallbackRunnerName имя fallback runner'а или null
     */
    public function __construct(
        public ?string $role,
        public string $runner,
        public string $label,
        public bool $isQualityGate,
        public ?string $fallbackRunnerName = null,
    ) {
    }
}
