<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain;

use LogicException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\ExecutionStrategyInterface;

/**
 * UseCase оркестрации цепочки AI-агентов.
 *
 * Чистый диспетчер: загружает цепочку, определяет стратегию выполнения,
 * делегирует execute/resume. Поведенческая логика инкапсулирована в стратегиях.
 */
class OrchestrateChainCommandHandler
{
    /**
     * @param ChainLoaderInterface $chainLoader загрузчик определения цепочки
     * @param iterable<ExecutionStrategyInterface> $strategies зарегистрированные стратегии выполнения
     */
    public function __construct(
        private ChainLoaderInterface $chainLoader,
        private iterable $strategies,
    ) {
    }

    /**
     * Выполняет оркестрацию цепочки.
     */
    public function __invoke(OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $chain = $this->chainLoader->load($command->chainName);
        $strategy = $this->resolveStrategy($chain);

        return $command->resumeDir !== null
            ? $strategy->resume($chain, $command)
            : $strategy->execute($chain, $command);
    }

    /**
     * Определяет стратегию выполнения по определению цепочки.
     */
    private function resolveStrategy(ChainDefinitionInterface $chain): ExecutionStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($chain)) {
                return $strategy;
            }
        }

        throw new LogicException(
            sprintf('No execution strategy found for chain "%s".', $chain->getName()),
        );
    }
}
