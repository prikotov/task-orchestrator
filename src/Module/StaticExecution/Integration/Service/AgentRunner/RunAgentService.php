<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Integration\Service\AgentRunner;

use Override;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface as OrchestratorRunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;

/**
 * Интеграционный сервис: делегирует запуск AI-агента в Orchestrator.
 *
 * VO общие между модулями — mapper не нужен.
 * Изолирует StaticExecution от конкретного Orchestrator Integration-слоя.
 */
final readonly class RunAgentService implements RunAgentServiceInterface
{
    public function __construct(
        private OrchestratorRunAgentServiceInterface $inner,
    ) {
    }

    #[Override]
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        return $this->inner->run($request, $retryPolicy);
    }
}
