<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * VO результата одного хода фасилитатора (Domain-аналог FacilitatorTurnResultDto).
 */
final readonly class FacilitatorTurnResultVo
{
    public function __construct(
        public DynamicRoundResultVo $roundResult,
        public bool $done,
        public ?string $nextRole,
        public ?string $synthesis,
        public ?string $challenge = null,
        public string $userPrompt = '',
    ) {
    }
}
