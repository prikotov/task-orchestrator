<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Контекст dynamic-цикла: роли, промпты, модель, таймаут.
 *
 * Перенесён из ChainDefinition.Domain.
 * Зависимость на PromptConfigurationVo заменена на DynamicLoopPromptConfigVo.
 *
 * @param list<string> $participants
 */
final readonly class DynamicLoopContextVo
{
    /** @param list<string> $participants */
    public function __construct(
        public string $facilitatorRole,
        public array $participants,
        public int $maxRounds,
        public string $topic,
        public DynamicLoopPromptConfigVo $promptConfiguration,
        public ?string $workingDir = null,
        public int $timeout = 300,
        public ?int $maxTime = null,
        /**
         * Дополнение от Лида при resume: текст аргумента task CLI-команды.
         *
         * Фасилитатор получает его отдельным блоком «Дополнение от Лида» в
         * continue-промпте (при пустом журнале — в start-промпте) и маршрутизирует
         * раунды по новой вводной. null — дополнения нет (обычный запуск или
         * resume без новой вводной), блок не рендерится.
         */
        public ?string $taskAddendum = null,
    ) {
    }
}
