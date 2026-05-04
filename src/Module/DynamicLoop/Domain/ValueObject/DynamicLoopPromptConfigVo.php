<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object конфигурации промптов dynamic-цикла.
 *
 * Инкапсулирует 7 промпт-полей для dynamic-цикла.
 * Копия ChainDefinition\PromptConfigurationVo, без зависимости от ChainDefinition.Domain.
 * Immutable, readonly.
 */
final readonly class DynamicLoopPromptConfigVo
{
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

    public function getBrainstormSystemPrompt(): string
    {
        return $this->brainstormSystemPrompt;
    }

    public function getFacilitatorAppendPrompt(): string
    {
        return $this->facilitatorAppendPrompt;
    }

    public function getFacilitatorStartPrompt(): string
    {
        return $this->facilitatorStartPrompt;
    }

    public function getFacilitatorContinuePrompt(): string
    {
        return $this->facilitatorContinuePrompt;
    }

    public function getFacilitatorFinalizePrompt(): string
    {
        return $this->facilitatorFinalizePrompt;
    }

    public function getParticipantAppendPrompt(): string
    {
        return $this->participantAppendPrompt;
    }

    public function getParticipantUserPrompt(): string
    {
        return $this->participantUserPrompt;
    }
}
