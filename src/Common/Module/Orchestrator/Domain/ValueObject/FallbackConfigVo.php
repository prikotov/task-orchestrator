<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * Value Object конфигурации fallback для роли.
 *
 * Определяет полную CLI-команду для запуска fallback-агента,
 * которая используется при ошибке основного runner'а.
 *
 * Первый элемент command — имя runner'а (регистрируется в ResolveAgentRunnerServiceInterface).
 * Остальные элементы — CLI-параметры (--model, --system-prompt, и т.д.).
 *
 * Пример YAML:
 *   fallback:
 *     command:
 *       - codex
 *       - --model
 *       - gpt-4o
 *       - --full-auto
 */
final readonly class FallbackConfigVo
{
    /**
     * @param list<string> $command полная CLI-команда fallback-агента.
     *        Первый элемент — имя runner'а, остальные — параметры.
     *        Не может быть пустым.
     */
    public function __construct(
        private array $command = [],
    ) {
    }

    /**
     * Возвращает имя fallback runner'а (первый элемент command).
     *
     * Если command пуст — возвращает null (fallback не сконфигурирован).
     */
    public function getRunnerName(): ?string
    {
        return $this->command[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getCommand(): array
    {
        return $this->command;
    }
}
