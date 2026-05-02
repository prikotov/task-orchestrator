<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Security;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\SecurityPolicyViolationException;

/**
 * Проверка chain-level security policy перед выполнением цепочки.
 *
 * Порт (interface) в Orchestrator Domain. Реализация находится
 * в SecurityPolicy Infrastructure (Dependency Inversion).
 *
 * Точки вмешательства:
 * - OrchestrateChainCommandHandler (вход в оркестрацию)
 * - ExecutionStrategy::execute() (начало выполнения)
 */
interface CheckChainSecurityServiceInterface
{
    /**
     * Проверяет, авторизован ли запуск цепочки с указанным именем и типом.
     *
     * Если цепочка запрещена — выбрасывает SecurityPolicyViolationException.
     * Если разрешена — метод завершается без возвращаемого значения (void).
     *
     * @param string $chainName имя цепочки для проверки
     * @param ChainTypeEnum $type тип цепочки (static, dynamic, conditional)
     *
     * @throws SecurityPolicyViolationException если цепочка не авторизована для выполнения
     */
    public function checkChainExecution(string $chainName, ChainTypeEnum $type): void;
}
