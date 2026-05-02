<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service;

use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;

/**
 * Domain Service: агрегация security policy checks.
 *
 * Объединяет chain-level checks (PermissionSet) и exec-level checks (ExecRule).
 * Выбрасывает исключения при нарушениях.
 */
interface SecurityPolicyServiceInterface
{
    /**
     * Проверяет, авторизован ли запуск цепочки.
     *
     * Проверяет chain-level permissions: имя цепочки и тип
     * должны быть разрешены через PermissionSet.
     *
     * @throws \TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\SecurityPolicyViolationException
     */
    public function checkChainExecution(
        string $chainName,
        string $chainType,
    ): void;

    /**
     * Проверяет, разрешена ли команда runner'а.
     *
     * Проверяет exec rules для target=command.
     *
     * @throws \TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\ExecPolicyViolationException
     */
    public function checkRunnerCommand(string $runnerName, string $task, ?string $tools = null): void;

    /**
     * Проверяет, разрешена ли shell-команда (quality gates).
     *
     * Проверяет exec rules для target=command.
     *
     * @throws \TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\ExecPolicyViolationException
     */
    public function checkShellCommand(string $command): void;
}
