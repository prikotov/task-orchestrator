<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

/**
 * Контекст dynamic-цепочки: роли, промпты, модель, таймаут.
 *
 * Содержит PromptConfigurationVo вместо отдельных промпт-полей.
 *
 * @param array<int, string> $participants
 */
final readonly class DynamicChainContextVo
{
    public function __construct(
        public string $facilitatorRole,
        public array $participants,
        public int $maxRounds,
        public string $topic,
        public PromptConfigurationVo $promptConfiguration,
        public ?string $workingDir = null,
        public int $timeout,
        public ?int $maxTime = null,
    ) {
    }
}
