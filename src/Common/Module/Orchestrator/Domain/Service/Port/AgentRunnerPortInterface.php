<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;

/**
 * Port-интерфейс движка AI-агента для Orchestrator Domain.
 *
 * Инкапсулирует вызов агента и retry-политику.
 * Реализация (Adapter) маппит VO и делегирует в AgentRunnerInterface.
 */
interface AgentRunnerPortInterface
{
    /**
     * Уникальное имя движка (например, 'pi', 'codex').
     */
    public function getName(): string;

    /**
     * Проверяет доступность движка в текущем окружении.
     */
    public function isAvailable(): bool;

    /**
     * Запускает агент с заданным запросом и опциональной retry-политикой.
     *
     * Retry инкапсулирован внутри реализации: вызывающая сторона
     * не знает о RetryableRunnerFactory.
     */
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo;
}
