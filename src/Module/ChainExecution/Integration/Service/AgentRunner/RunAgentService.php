<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\AgentRunner;

use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Integration\RunAgentServiceInterface as OrchestratorRunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;

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
    public function run(ChainRunRequestVo $request, ?ExecutionRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        return $this->inner->run($request, $retryPolicy);
    }
}
