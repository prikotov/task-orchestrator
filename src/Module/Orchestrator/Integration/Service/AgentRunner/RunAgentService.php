<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Integration\Service\AgentRunner;

use Override;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;

/**
 * Интеграционный сервис: делегирует вызовы через AgentRunner Application use cases.
 *
 * Stateless: runner name берётся из ChainRunRequestVo::getRunnerName().
 * Маппит Orchestrator Domain VO → AgentRunner Application DTO
 * и обратно. Retry инкапсулирован внутри AgentRunner Application.
 */
final readonly class RunAgentService implements RunAgentServiceInterface
{
    public function __construct(
        private RunAgentCommandHandler $runAgentHandler,
        private AgentDtoMapper $mapper,
    ) {
    }

    #[Override]
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        $command = $this->mapper->mapToRunAgentCommand($request, $retryPolicy);
        $resultDto = ($this->runAgentHandler)($command);

        return $this->mapper->mapFromRunAgentResultDto($resultDto);
    }
}
