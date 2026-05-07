<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\Agent\RunAgent;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Agent\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;

/**
 * Application-level API для прямого запуска агента.
 *
 * Принимает ChainRunRequestVo напрямую, без промежуточного Query-объекта.
 * Integration-слой другого модуля вызывает этот QueryHandler
 * (Integration → foreign Application — разрешено Deptrac).
 *
 * В отличие от RunAgentCommandHandler, не резолвит промпт и не модифицирует request.
 */
class RunAgentQueryHandler
{
    public function __construct(
        private RunAgentServiceInterface $agentRunner,
    ) {
    }

    public function run(ChainRunRequestVo $request, ?ExecutionRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        return $this->agentRunner->run($request, $retryPolicy);
    }
}
