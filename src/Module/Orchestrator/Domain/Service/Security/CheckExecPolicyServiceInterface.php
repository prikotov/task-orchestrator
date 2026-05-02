<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Security;

use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\ExecPolicyViolationException;

/**
 * Проверка exec-level security policy для runner-команд и shell-вызовов.
 *
 * Порт (interface) в Orchestrator Domain. Реализация находится
 * в SecurityPolicy Infrastructure (Dependency Inversion).
 *
 * Точки вмешательства:
 * - RunAgentServiceInterface::run() (вызов runner'а)
 * - QualityGateRunnerInterface::run() (shell-команды quality gates)
 * - RunDynamicLoopService::execute() (dynamic turn)
 */
interface CheckExecPolicyServiceInterface
{
    /**
     * Проверяет, разрешена ли команда runner'а с заданными параметрами.
     *
     * Проверяет exec rules для target=runner и target=tool.
     * Если команда запрещена — выбрасывает ExecPolicyViolationException.
     * Если разрешена — метод завершается без возвращаемого значения (void).
     *
     * @param string $runnerName имя runner'а (например, "openai", "local-shell")
     * @param string $task задача/команда для выполнения
     * @param string|null $tools опциональный список инструментов (null если не применимо)
     *
     * @throws ExecPolicyViolationException если команда runner'а запрещена политикой
     */
    public function checkRunnerCommand(string $runnerName, string $task, ?string $tools = null): void;

    /**
     * Проверяет, разрешена ли shell-команда (quality gates, exec-шаги).
     *
     * Проверяет exec rules для target=command.
     * Если команда запрещена — выбрасывает ExecPolicyViolationException.
     * Если разрешена — метод завершается без возвращаемого значения (void).
     *
     * @param string $command shell-команда для проверки
     *
     * @throws ExecPolicyViolationException если shell-команда запрещена политикой
     */
    public function checkShellCommand(string $command): void;
}
