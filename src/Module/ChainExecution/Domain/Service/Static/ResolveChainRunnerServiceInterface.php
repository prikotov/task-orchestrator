<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionFallbackConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;

/**
 * Резолвит fallback runner при ошибке основного.
 */
interface ResolveChainRunnerServiceInterface
{
    /**
     * Пытается выполнить шаг через fallback runner при ошибке основного.
     */
    public function tryFallbackRunner(
        ExecutionFallbackConfigVo $fallbackConfig,
        string $role,
        string $primaryRunnerName,
        ?ExecutionRetryPolicyVo $retryPolicy,
        ChainRunRequestVo $primaryRequest,
        ?string $promptFile = null,
    ): ?ChainRunResultVo;
}
