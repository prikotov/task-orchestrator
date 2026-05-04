<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;

/**
 * Стратегия выполнения цепочки оркестрации.
 *
 * Каждая реализация инкапсулирует один поведенческий путь (static, dynamic, conditional).
 * CommandHandler делегирует выполнение стратегии через supports() + execute()/resume().
 *
 * @see https://github.com/prikotov/task-orchestrator/blob/main/docs/adr/006-execution-strategy-composition.md
 */
interface ExecutionStrategyInterface
{
    /**
     * Выполняет цепочку с нуля.
     */
    public function execute(ChainDefinitionInterface $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto;

    /**
     * Возобновляет прерванную цепочку.
     */
    public function resume(ChainDefinitionInterface $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto;

    /**
     * Определяет, поддерживает ли стратегия данную цепочку.
     */
    public function supports(ChainDefinitionInterface $chain): bool;
}
