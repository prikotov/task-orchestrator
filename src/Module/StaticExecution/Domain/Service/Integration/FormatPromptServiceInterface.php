<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Integration;

/**
 * Интеграционный сервис форматирования промптов для StaticExecution Domain.
 *
 * Делегирует вызов в Orchestrator\Domain\Service\Chain\Shared\PromptFormatterInterface.
 * Работает только с примитивами и array — Orchestrator VO не импортирует.
 */
interface FormatPromptServiceInterface
{
    /**
     * Формирует контекст промпта шага static-цепочки от предыдущего агента.
     */
    public function buildStaticContext(
        string $role,
        string $previousOutput,
        string $task,
    ): string;

    /**
     * Подставляет путь к файлу промпта в команду вместо маркера или добавляет флаг.
     *
     * @param list<string> $command
     * @return list<string>
     */
    public function resolveSlot(
        array $command,
        string $marker,
        string $sessionFilePath,
        string $fallbackKey,
    ): array;
}
