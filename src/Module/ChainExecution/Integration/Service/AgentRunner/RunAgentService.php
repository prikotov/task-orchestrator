<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\AgentRunner;

use Override;
use TaskOrchestrator\Common\Component\CommandBus\CommandBusComponentInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Agent\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;

/**
 * Integration-адаптер: маппит VO → DTO и вызывает AgentRunner Use Case.
 *
 * ACL (Anti-Corruption Layer): ChainExecution не зависит от AgentRunner Domain напрямую,
 * только от AgentRunner Application (Command Handler).
 */
final readonly class RunAgentService implements RunAgentServiceInterface
{
    public function __construct(
        private CommandBusComponentInterface $commandBus,
        private AgentDtoMapper $mapper,
    ) {
    }

    #[Override]
    public function run(ChainRunRequestVo $request, ?ExecutionRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        $command = $this->mapper->mapToRunAgentCommand($request, $retryPolicy);

        /** @var RunAgentResultDto $result */
        $result = $this->commandBus->execute($command);

        return $this->mapper->mapFromRunAgentResultDto($result);
    }
}
