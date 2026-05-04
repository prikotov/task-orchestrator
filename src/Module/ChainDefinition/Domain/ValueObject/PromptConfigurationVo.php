<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object конфигурации промптов dynamic-цепочки.
 *
 * Инкапсулирует 7 промпт-полей, которые ранее протаскивались через 4 слоя
 * оркестрации как отдельные параметры. Immutable, readonly.
 */
final readonly class PromptConfigurationVo
{
    /**
     * @param string $brainstormSystemPrompt базовый системный промпт (упрощённый Pi default) для --system-prompt
     * @param string $facilitatorAppendPrompt промпт фасилитатора для --append-system-prompt (%s → participants)
     * @param string $facilitatorStartPrompt промпт первого вызова фасилитатора (%s → topic)
     * @param string $facilitatorContinuePrompt промпт продолжения фасилитатора (%s → topic, %s → journal, %s → history)
     * @param string $facilitatorFinalizePrompt промпт финализации (%s → topic, %s → history)
     * @param string $participantAppendPrompt промпт участника для --append-system-prompt (%s → role_file)
     * @param string $participantUserPrompt пользовательский промпт участника (%s → topic, %s → history)
     */
    public function __construct(
        private string $brainstormSystemPrompt,
        private string $facilitatorAppendPrompt,
        private string $facilitatorStartPrompt,
        private string $facilitatorContinuePrompt,
        private string $facilitatorFinalizePrompt,
        private string $participantAppendPrompt,
        private string $participantUserPrompt,
    ) {
        if (
            trim($this->brainstormSystemPrompt) === ''
            || trim($this->facilitatorAppendPrompt) === ''
            || trim($this->facilitatorStartPrompt) === ''
            || trim($this->facilitatorContinuePrompt) === ''
            || trim($this->facilitatorFinalizePrompt) === ''
            || trim($this->participantAppendPrompt) === ''
            || trim($this->participantUserPrompt) === ''
        ) {
            throw new InvalidArgumentException('All prompt fields must be non-empty.');
        }
    }

    /**
     * Возвращает базовый системный промпт для --system-prompt.
     */
    public function getBrainstormSystemPrompt(): string
    {
        return $this->brainstormSystemPrompt;
    }

    /**
     * Возвращает промпт фасилитатора для --append-system-prompt (%s → participants).
     */
    public function getFacilitatorAppendPrompt(): string
    {
        return $this->facilitatorAppendPrompt;
    }

    /**
     * Возвращает промпт первого вызова фасилитатора (%s → topic).
     */
    public function getFacilitatorStartPrompt(): string
    {
        return $this->facilitatorStartPrompt;
    }

    /**
     * Возвращает промпт продолжения фасилитатора (%s → topic, %s → journal, %s → history).
     */
    public function getFacilitatorContinuePrompt(): string
    {
        return $this->facilitatorContinuePrompt;
    }

    /**
     * Возвращает промпт финализации (%s → topic, %s → history).
     */
    public function getFacilitatorFinalizePrompt(): string
    {
        return $this->facilitatorFinalizePrompt;
    }

    /**
     * Возвращает промпт участника для --append-system-prompt (%s → role_file).
     */
    public function getParticipantAppendPrompt(): string
    {
        return $this->participantAppendPrompt;
    }

    /**
     * Возвращает пользовательский промпт участника (%s → topic, %s → history).
     */
    public function getParticipantUserPrompt(): string
    {
        return $this->participantUserPrompt;
    }
}
