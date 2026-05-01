<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Integration\Service;

use Override;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Port\AgentRunnerPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;

/**
 * ACL-адаптер: делегирует запуск агента в Orchestrator Integration-порт.
 *
 * Изолирует StaticExecution Domain от Orchestrator RunAgentServiceInterface.
 * Адаптер необходим для Deptrac: StaticExecution Domain не зависит
 * от Orchestrator Domain Service, только от собственного Port.
 */
final readonly class AgentRunnerAdapter implements AgentRunnerPortInterface
{
    public function __construct(
        private RunAgentServiceInterface $inner,
    ) {
    }

    #[Override]
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        return $this->inner->run($request, $retryPolicy);
    }
}
