<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Integration\Service;

use Override;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Port\PromptFormatterPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\PromptFormatterInterface;

/**
 * ACL-адаптер: делегирует форматирование промптов в Orchestrator Shared-сервис.
 *
 * Изолирует StaticExecution Domain от Orchestrator PromptFormatterInterface.
 * Адаптер необходим для Deptrac: StaticExecution Domain не зависит
 * от Orchestrator Domain Service, только от собственного Port.
 */
final readonly class PromptFormatterAdapter implements PromptFormatterPortInterface
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
