<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent;

/**
 * DTO команды запуска AI-агента.
 *
 * Транспортный объект на границе Application-слоя модуля AgentRunner.
 * Не содержит бизнес-логики. Все поля — скаляры и массивы скаляров.
 */
final readonly class RunAgentCommand
{
    /**
     * @param string $runnerName имя движка (пустая строка = default)
     * @param string $role роль агента
     * @param string $task задача для агента
     * @param string|null $systemPrompt системный промпт
     * @param string|null $previousContext предыдущий контекст
     * @param string|null $model модель AI
     * @param string|null $tools набор инструментов
     * @param string|null $workingDir рабочая директория
     * @param int $timeout таймаут в секундах
     * @param int $maxContextLength максимальная длина контекста
     * @param list<string> $command полная CLI-команда
     * @param list<string> $runnerArgs доп. аргументы runner'а
     * @param int|null $retryMaxRetries макс. попыток retry (null = без retry)
     * @param int $retryInitialDelayMs начальная задержка retry (мс)
     * @param int $retryMaxDelayMs макс. задержка retry (мс)
     * @param float $retryMultiplier множитель exponential backoff
     * @param bool $noContextFiles отключить загрузку контекстных файлов проекта
     */
    public function __construct(
        public string $runnerName,
        public string $role,
        public string $task,
        public ?string $systemPrompt = null,
        public ?string $previousContext = null,
        public ?string $model = null,
        public ?string $tools = null,
        public ?string $workingDir = null,
        public int $timeout = 300,
        public int $maxContextLength = 50000,
        public array $command = [],
        public array $runnerArgs = [],
        public ?int $retryMaxRetries = null,
        public int $retryInitialDelayMs = 1000,
        public int $retryMaxDelayMs = 30000,
        public float $retryMultiplier = 2.0,
        public bool $noContextFiles = false,
    ) {
    }
}
