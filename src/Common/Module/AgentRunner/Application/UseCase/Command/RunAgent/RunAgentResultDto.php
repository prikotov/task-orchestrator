<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent;

/**
 * DTO результата запуска AI-агента.
 *
 * Транспортный объект на границе Application-слоя модуля AgentRunner.
 * Не содержит бизнес-логики. Все поля — скаляры.
 */
final readonly class RunAgentResultDto
{
    public function __construct(
        public string $outputText,
        public int $inputTokens,
        public int $outputTokens,
        public int $cacheReadTokens,
        public int $cacheWriteTokens,
        public float $cost,
        public int $exitCode,
        public ?string $model,
        public int $turns,
        public bool $isError,
        public ?string $errorMessage,
    ) {
    }
}
