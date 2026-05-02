<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Orchestrator;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ExecutionStrategyInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Security\CheckChainSecurityServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;

/**
 * Decorator для ExecutionStrategyInterface — проверяет chain-level security policy.
 *
 * Точки вмешательства #1 + #2:
 * - OrchestrateChainCommandHandler (вход в оркестрацию)
 * - ExecutionStrategy::execute() / resume() (начало выполнения)
 *
 * Проверяет chain-level authorization через CheckChainSecurityServiceInterface.
 * Если chain policy violation — выбрасывает SecurityPolicyViolationException
 * ДО выполнения стратегии.
 *
 * Применяется ко ВСЕМ стратегиям (Static, Dynamic, Conditional).
 *
 * Расположение: SecurityPolicy/Infrastructure/Orchestrator/ — Dependency Inversion.
 *
 * @see ExecutionStrategyInterface
 * @see CheckChainSecurityServiceInterface
 */
final readonly class SecurityPolicyExecutionStrategyDecorator implements ExecutionStrategyInterface
{
    public function __construct(
        private ExecutionStrategyInterface $decoratedStrategy,
        private CheckChainSecurityServiceInterface $chainSecurityPolicy,
    ) {
    }

    #[Override]
    public function execute(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $shared = $chain->getSharedDefinition();

        $this->chainSecurityPolicy->checkChainExecution(
            chainName: $shared->getName(),
            type: $shared->getType(),
        );

        return $this->decoratedStrategy->execute($chain, $command);
    }

    #[Override]
    public function resume(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $shared = $chain->getSharedDefinition();

        $this->chainSecurityPolicy->checkChainExecution(
            chainName: $shared->getName(),
            type: $shared->getType(),
        );

        return $this->decoratedStrategy->resume($chain, $command);
    }

    #[Override]
    public function supports(ChainDefinitionVo $chain): bool
    {
        return $this->decoratedStrategy->supports($chain);
    }
}
