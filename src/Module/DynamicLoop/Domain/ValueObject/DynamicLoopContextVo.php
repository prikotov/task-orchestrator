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
        private string $facilitatorRole,
        private array $participants,
        private int $maxRounds,
        private string $topic,
        private DynamicLoopPromptConfigVo $promptConfiguration,
        private ?string $workingDir = null,
        private int $timeout = 300,
        private ?int $maxTime = null,
        /**
         * Дополнение от Лида при resume: текст аргумента task CLI-команды.
         *
         * Фасилитатор получает его отдельным блоком «Дополнение от Лида» в
         * continue-промпте (при пустом журнале — в start-промпте) и маршрутизирует
         * раунды по новой вводной. null — дополнения нет (обычный запуск или
         * resume без новой вводной), блок не рендерится.
         */
        private ?string $taskAddendum = null,
    ) {
    }

    public function getFacilitatorRole(): string
    {
        return $this->facilitatorRole;
    }

    /**
     * @return list<string>
     */
    public function getParticipants(): array
    {
        return $this->participants;
    }

    public function getMaxRounds(): int
    {
        return $this->maxRounds;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getPromptConfiguration(): DynamicLoopPromptConfigVo
    {
        return $this->promptConfiguration;
    }

    public function getWorkingDir(): ?string
    {
        return $this->workingDir;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getMaxTime(): ?int
    {
        return $this->maxTime;
    }

    /**
     * Дополнение от Лида при resume; null — дополнения нет.
     */
    public function getTaskAddendum(): ?string
    {
        return $this->taskAddendum;
    }
}
