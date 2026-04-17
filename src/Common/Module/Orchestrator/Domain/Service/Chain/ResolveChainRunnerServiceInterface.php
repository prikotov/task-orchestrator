<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FallbackConfigVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RetryPolicyVo;

/**
 * Резолвит эффективный runner: retry-декоратор и fallback при ошибке.
 */
interface ResolveChainRunnerServiceInterface
{
    /**
     * Создаёт runner с retry-декоратором, если политика retry задана и включена.
     */
    public function createRunnerWithRetry(
        AgentRunnerInterface $runner,
        ?RetryPolicyVo $retryPolicy,
    ): AgentRunnerInterface;

    /**
     * Пытается выполнить шаг через fallback runner при ошибке основного.
     *
     * Возвращает null, если fallback runner не найден или выбросил исключение.
     */
    public function tryFallbackRunner(
        FallbackConfigVo $fallbackConfig,
        string $role,
        string $primaryRunnerName,
        ?RetryPolicyVo $retryPolicy,
        AgentRunRequestVo $primaryRequest,
        ?string $promptFile = null,
    ): ?AgentResultVo;
}
