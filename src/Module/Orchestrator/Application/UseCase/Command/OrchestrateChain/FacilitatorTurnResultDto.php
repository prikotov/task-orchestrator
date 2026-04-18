<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain;

/**
 * DTO результата одного хода фасилитатора.
 *
 * Содержит метрики раунда и распарсенное решение фасилитатора.
 * Domain VO FacilitatorResponseVo развёрнут в примитивы для соблюдения
 * границ слоёв (ApplicationDto не зависит от DomainVo).
 */
final readonly class FacilitatorTurnResultDto
{
    public function __construct(
        public DynamicRoundResultDto $roundResult,
        public bool $done,
        public ?string $nextRole,
        public ?string $synthesis,
        public ?string $challenge = null,
        public string $userPrompt = '',
    ) {
    }
}
