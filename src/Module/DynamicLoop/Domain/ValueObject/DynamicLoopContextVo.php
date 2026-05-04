<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Контекст dynamic-цикла: роли, промпты, модель, таймаут.
 *
 * Перенесён из ChainDefinition.Domain.
 * Зависимость на PromptConfigurationVo заменена на DynamicLoopPromptConfigVo.
 *
 * @param array<int, string> $participants
 */
final readonly class DynamicLoopContextVo
{
    public function __construct(
        public string $facilitatorRole,
        public array $participants,
        public int $maxRounds,
        public string $topic,
        public DynamicLoopPromptConfigVo $promptConfiguration,
        public ?string $workingDir = null,
        public int $timeout = 300,
        public ?int $maxTime = null,
    ) {
    }
}
