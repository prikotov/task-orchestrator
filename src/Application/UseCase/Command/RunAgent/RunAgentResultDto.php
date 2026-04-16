<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Application\UseCase\Command\RunAgent;

/**
 * DTO результата запуска одного AI-агента.
 *
 * Транспортный объект на границе Application ↔ Presentation.
 * Содержит только примитивы.
 */
final readonly class RunAgentResultDto
{
    public function __construct(
        public string $outputText,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadTokens = 0,
        public int $cacheWriteTokens = 0,
        public float $cost = 0.0,
        public int $exitCode = 0,
        public ?string $model = null,
        public int $turns = 0,
        public bool $isError = false,
        public ?string $errorMessage = null,
    ) {
    }
}
