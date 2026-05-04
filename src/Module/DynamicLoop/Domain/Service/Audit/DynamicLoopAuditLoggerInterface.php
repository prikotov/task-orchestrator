<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Dto\DynamicLoopAuditDto;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;

/**
 * Интерфейс JSONL audit-логгера для dynamic-цикла.
 *
 * Port/Adapter: DynamicLoop.Domain определяет свой Port (интерфейс),
 * Infrastructure предоставляет один Adapter (JsonlAuditLogger),
 * реализующий и этот интерфейс, и AuditLoggerInterface из ChainDefinition.
 *
 * Методы logChainStart/logStepStart — общие с AuditLoggerInterface (одинаковая сигнатура).
 * Методы logDynamicStepResult/logDynamicChainResult — уникальные (DynamicRoundResultVo вместо ChainRunResultVo).
 */
interface DynamicLoopAuditLoggerInterface
{
    /**
     * Логирует старт dynamic-цикла.
     */
    public function logChainStart(string $chainName, string $task): void;

    /**
     * Логирует старт шага (перед вызовом runner'а).
     */
    public function logStepStart(string $chainName, int $stepNumber, string $role, string $runner): void;

    /**
     * Логирует результат шага dynamic-цикла.
     */
    public function logDynamicStepResult(
        string $chainName,
        int $stepNumber,
        string $role,
        string $runner,
        DynamicRoundResultVo $result,
        float $durationMs,
    ): void;

    /**
     * Логирует финальный результат dynamic-цикла.
     */
    public function logDynamicChainResult(DynamicLoopAuditDto $audit): void;
}
