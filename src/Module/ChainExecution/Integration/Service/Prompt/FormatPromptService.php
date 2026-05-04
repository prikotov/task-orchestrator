<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\Prompt;

use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Shared\PromptFormatterInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\FormatPromptServiceInterface;

/**
 * Интеграционный сервис: делегирует форматирование промптов в Orchestrator Shared-сервис.
 *
 * Изолирует StaticExecution от Orchestrator PromptFormatterInterface.
 */
final readonly class FormatPromptService implements FormatPromptServiceInterface
{
    public function __construct(
        private PromptFormatterInterface $inner,
    ) {
    }

    #[Override]
    public function buildStaticContext(
        string $role,
        string $previousOutput,
        string $task,
    ): string {
        return $this->inner->buildStaticContext($role, $previousOutput, $task);
    }

    #[Override]
    public function resolveSlot(
        array $command,
        string $marker,
        string $sessionFilePath,
        string $fallbackKey,
    ): array {
        return $this->inner->resolveSlot($command, $marker, $sessionFilePath, $fallbackKey);
    }
}
