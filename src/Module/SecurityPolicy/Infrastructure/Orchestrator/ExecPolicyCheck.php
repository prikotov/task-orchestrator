<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Orchestrator;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Security\CheckExecPolicyServiceInterface;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\SecurityPolicyServiceInterface;

/**
 * Infrastructure-реализация port CheckExecPolicyServiceInterface.
 *
 * Делегирует проверку в SecurityPolicyServiceInterface (Domain Service).
 * Проверяет runner-команды и shell-команды на соответствие exec policy.
 *
 * Расположение: SecurityPolicy/Infrastructure/Orchestrator/ — Dependency Inversion.
 * Port определён в Orchestrator Domain, реализация — в SecurityPolicy Infrastructure.
 *
 * @see CheckExecPolicyServiceInterface
 * @see SecurityPolicyServiceInterface
 */
final readonly class ExecPolicyCheck implements CheckExecPolicyServiceInterface
{
    public function __construct(
        private SecurityPolicyServiceInterface $securityPolicyService,
    ) {
    }

    #[Override]
    public function checkRunnerCommand(string $runnerName, string $task, ?string $tools = null): void
    {
        $this->securityPolicyService->checkRunnerCommand(
            runnerName: $runnerName,
            task: $task,
            tools: $tools,
        );
    }

    #[Override]
    public function checkShellCommand(string $command): void
    {
        $this->securityPolicyService->checkShellCommand($command);
    }
}
