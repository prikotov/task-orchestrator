<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Domain\Service;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FallbackConfigVo;

/**
 * Резолвит fallback runner при ошибке основного.
 *
 * Retry инкапсулирован в RunAgentServiceInterface — отдельный метод createRunnerWithRetry не нужен.
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
