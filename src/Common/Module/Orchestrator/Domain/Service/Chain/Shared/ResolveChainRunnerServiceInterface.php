<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FallbackConfigVo;

/**
 * Резолвит fallback runner при ошибке основного.
 *
 * Retry инкапсулирован в AgentRunnerPortInterface — отдельный метод createRunnerWithRetry не нужен.
 */
interface ResolveChainRunnerServiceInterface
{
    /**
     * Пытается выполнить шаг через fallback runner при ошибке основного.
     *
     * Возвращает null, если fallback runner не найден или выбросил исключение.
     */
    public function tryFallbackRunner(
        FallbackConfigVo $fallbackConfig,
        string $role,
        string $primaryRunnerName,
        ?ChainRetryPolicyVo $retryPolicy,
        ChainRunRequestVo $primaryRequest,
        ?string $promptFile = null,
    ): ?ChainRunResultVo;
}
