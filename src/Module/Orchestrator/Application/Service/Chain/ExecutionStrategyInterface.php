<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;

/**
 * Стратегия выполнения цепочки оркестрации.
 *
 * Каждая реализация инкапсулирует один поведенческий путь (static, dynamic, и т.д.).
 * CommandHandler делегирует выполнение стратегии через supports() + execute()/resume().
 *
 * @see https://github.com/prikotov/task-orchestrator/blob/main/docs/adr/006-execution-strategy-composition.md
 */
interface ExecutionStrategyInterface
{
    /**
     * Выполняет цепочку с нуля.
     */
    public function execute(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto;

    /**
     * Возобновляет прерванную цепочку.
     */
    public function resume(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto;

    /**
     * Определяет, поддерживает ли стратегия данную цепочку.
     */
    public function supports(ChainDefinitionVo $chain): bool;
}
