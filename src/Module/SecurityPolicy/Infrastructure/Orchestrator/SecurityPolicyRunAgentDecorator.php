<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Orchestrator;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Security\CheckExecPolicyServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;

/**
 * Decorator для RunAgentServiceInterface — проверяет exec policy перед runner-вызовом.
 *
 * Точка вмешательства #4: RunAgentServiceInterface::run().
 * Проверяет runner, task и tools через CheckExecPolicyServiceInterface.
 * Если exec policy violation — выбрасывает ExecPolicyViolationException
 * ДО вызова реального runner'а.
 *
 * Порядок decoration (по ADR-010):
 * SecurityPolicyRunAgentDecorator → RetryingAgentRunner → CircuitBreakerAgentRunner → Concrete Runner.
 * Security check — outermost (первый), чтобы не тратить retry на запрещённые команды.
 *
 * Расположение: SecurityPolicy/Infrastructure/Orchestrator/ — Dependency Inversion.
 *
 * @see RunAgentServiceInterface
 * @see CheckExecPolicyServiceInterface
 */
final readonly class SecurityPolicyRunAgentDecorator implements RunAgentServiceInterface
{
    public function __construct(
        private RunAgentServiceInterface $decoratedService,
        private CheckExecPolicyServiceInterface $execPolicy,
    ) {
    }

    #[Override]
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        $this->execPolicy->checkRunnerCommand(
            runnerName: $request->getRunnerName() ?? 'default',
            task: $request->getTask(),
            tools: $request->getTools(),
        );

        return $this->decoratedService->run($request, $retryPolicy);
    }
}
