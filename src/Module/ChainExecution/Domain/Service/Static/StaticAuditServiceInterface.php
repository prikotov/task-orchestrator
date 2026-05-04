<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticChainAuditVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;

/**
 * Интерфейс audit-логирования для static-цепочки.
 *
 * Инкапсулирует audit-ответственность внутри StaticExecution Domain.
 * Integration-реализация маппит StaticExecution VO → Orchestrator DTO
 * и делегирует в Orchestrator AuditLoggerInterface.
 */
interface StaticAuditServiceInterface
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
     * Принимает StaticStepResultVo — Integration-слой извлекает данные
     * и маппит в Orchestrator ChainRunResultVo.
     */
    public function logStepResult(
        string $chainName,
        int $stepNumber,
        string $role,
        string $runner,
        StaticStepResultVo $stepResult,
        float $durationMs,
    ): void;

    /**
     * Логирует финальный результат цепочки.
     */
    public function logChainResult(StaticChainAuditVo $audit): void;
}
