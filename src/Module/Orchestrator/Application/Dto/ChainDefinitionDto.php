<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Dto;

/**
 * DTO определения цепочки оркестрации.
 *
 * Транспортный объект на границе Application ↔ Presentation.
 * Маппится из Domain VO ChainDefinitionVo в Infrastructure-адаптере.
 * Содержит только данные, необходимые Presentation-слою для отображения.
 */
final readonly class ChainDefinitionDto
{
    /**
     * @param string $name имя цепочки
     * @param bool $isDynamic является ли цепочка динамической
     * @param string|null $facilitator роль фасилитатора (dynamic)
     * @param list<string> $participants роли участников (dynamic)
     * @param int $maxRounds лимит раундов (dynamic)
     * @param list<ChainStepDto> $steps шаги цепочки (static)
     */
    public function __construct(
        public string $name,
        public bool $isDynamic,
        public ?string $facilitator,
        public array $participants,
        public int $maxRounds,
        public array $steps,
    ) {
    }
}
