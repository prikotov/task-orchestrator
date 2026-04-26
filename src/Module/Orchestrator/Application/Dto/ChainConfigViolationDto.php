<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Dto;

/**
 * DTO нарушения конфигурации цепочки.
 *
 * Транспортный объект на границе Application ↔ Presentation.
 * Маппится из Domain VO ChainConfigViolationVo в Infrastructure-адаптере.
 */
final readonly class ChainConfigViolationDto
{
    /**
     * @param string $chainName имя цепочки, в которой обнаружено нарушение
     * @param string|null $field путь к полю (например, 'steps[0].role') или null
     * @param string $message человекочитаемое описание нарушения
     */
    public function __construct(
        public string $chainName,
        public ?string $field,
        public string $message,
    ) {
    }
}
