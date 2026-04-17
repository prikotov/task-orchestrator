<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Adapter;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use Override;

/**
 * Adapter: делегирует вызовы AgentRunnerInterface, маппит VO Orchestrator ↔ AgentRunner.
 *
 * Retry инкапсулирован: если retryPolicy задана, оборачивает runner через RetryableRunnerFactory.
 */
final readonly class AgentRunnerAdapter implements AgentRunnerPortInterface
{
    public function __construct(
        private AgentRunnerInterface $runner,
        private RetryableRunnerFactoryInterface $retryableRunnerFactory,
        private AgentVoMapper $mapper,
    ) {
    }

    #[Override]
    public function getName(): string
    {
        return $this->runner->getName();
    }

    #[Override]
    public function isAvailable(): bool
    {
        return $this->runner->isAvailable();
    }

    #[Override]
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        $agentRequest = $this->mapper->mapToAgentRequest($request);
        $retryPolicyVo = $this->mapper->mapToAgentRetryPolicy($retryPolicy);

        $effectiveRunner = ($retryPolicyVo !== null)
            ? $this->retryableRunnerFactory->createRetryableRunner($this->runner, $retryPolicyVo)
            : $this->runner;

        $agentResult = $effectiveRunner->run($agentRequest->withTruncatedContext());

        return $this->mapper->mapFromAgentResult($agentResult);
    }
}
