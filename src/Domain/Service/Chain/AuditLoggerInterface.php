<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\Dto\ChainResultAuditDto;
use TasK\Orchestrator\Domain\ValueObject\AgentResultVo;

/**
 * Интерфейс JSONL audit-логгера оркестратора AI-агентов.
 *
 * Логирует события выполнения цепочки (start/result шагов и цепочки)
 * в append-only JSONL-файл для воспроизводимости и анализа.
 *
 * Реализация — Infrastructure-слой (запись на диск).
 */
interface AuditLoggerInterface
{
    /**
     * Логирует старт цепочки.
     */
    public function logChainStart(string $chainName, string $task): void;

    /**
     * Логирует старт шага (перед вызовом runner'а).
     */
    public function logStepStart(string $chainName, int $stepNumber, string $role, string $runner): void;

    /**
     * Логирует результат шага (после вызова runner'а).
     *
     * Для agent-шагов передаётся реальный AgentResultVo.
     * Для quality_gate-шагов — синтетический AgentResultVo (isError=false, tokens=0, cost=0).
     */
    public function logStepResult(
        string $chainName,
        int $stepNumber,
        string $role,
        string $runner,
        AgentResultVo $result,
        float $durationMs,
    ): void;

    /**
     * Логирует финальный результат цепочки.
     */
    public function logChainResult(ChainResultAuditDto $audit): void;
}
