<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\ValueObject;

/**
 * Value Object ответа фасилитатора динамической цепочки.
 *
 * Фасилитатор возвращает JSON-решение:
 * - {next_role: "architect"} — дать слово участнику
 * - {done: true, synthesis: "..."} — завершить brainstorm.
 *
 * Immutable. Создаётся через фабричные методы.
 * Парсинг текстового ответа LLM вынесен в FacilitatorResponseParserService.
 */
final readonly class FacilitatorResponseVo
{
    private function __construct(
        private bool $done,
        private ?string $nextRole,
        private ?string $synthesis,
        private ?string $challenge = null,
    ) {
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function getNextRole(): ?string
    {
        return $this->nextRole;
    }

    public function getSynthesis(): ?string
    {
        return $this->synthesis;
    }

    public function getChallenge(): ?string
    {
        return $this->challenge;
    }

    /**
     * Создаёт ответ «продолжить с указанной ролью».
     */
    public static function createFromNextRole(string $role, ?string $challenge = null): self
    {
        return new self(done: false, nextRole: $role, synthesis: null, challenge: $challenge);
    }

    /**
     * Создаёт ответ «завершить с synthesis».
     */
    public static function createFromDone(string $synthesis): self
    {
        return new self(done: true, nextRole: null, synthesis: $synthesis);
    }
}
