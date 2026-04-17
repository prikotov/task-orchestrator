<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Integration\Adapter;

use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommand;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use Override;

/**
 * ACL-адаптер: делегирует вызовы через AgentRunner Application use cases.
 *
 * Маппит Orchestrator Domain VO → AgentRunner Application DTO
 * и обратно. Retry инкапсулирован внутри AgentRunner Application.
 */
final readonly class AgentRunnerAdapter implements AgentRunnerPortInterface
{
    public function __construct(
        private RunAgentCommandHandler $runAgentHandler,
        private AgentDtoMapper $mapper,
        private string $runnerName,
        private bool $runnerAvailable,
    ) {
    }

    #[Override]
    public function getName(): string
    {
        return $this->runnerName;
    }

    #[Override]
    public function isAvailable(): bool
    {
        return $this->runnerAvailable;
    }

    #[Override]
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        $command = $this->mapper->mapToRunAgentCommand($request, $this->runnerName, $retryPolicy);
        $resultDto = ($this->runAgentHandler)($command);

        return $this->mapper->mapFromRunAgentResultDto($resultDto);
    }
}
