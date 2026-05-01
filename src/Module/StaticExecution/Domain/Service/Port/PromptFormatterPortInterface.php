<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Port;

/**
 * Port: форматирование промптов для static-цепочки.
 *
 * ACL-интерфейс — изолирует StaticExecution Domain от Orchestrator Shared-сервиса.
 * Адаптер в StaticExecution\Integration делегирует в Orchestrator\PromptFormatterInterface.
 */
interface PromptFormatterPortInterface
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
