<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain;

use LogicException;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Contract\Chain\ExecutionStrategyInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\ChainDefinition\ChainDefinitionProviderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionChainInfoVo;

/**
 * UseCase оркестрации цепочки AI-агентов.
 *
 * Чистый диспетчер: загружает идентификацию цепочки, определяет стратегию выполнения,
 * делегирует execute/resume. Поведенческая логика инкапсулирована в стратегиях.
 */
class OrchestrateChainCommandHandler
{
    /**
     * @param ChainDefinitionProviderInterface $chainProvider провайдер определения цепочки
     * @param iterable<ExecutionStrategyInterface> $strategies зарегистрированные стратегии выполнения
     */
    public function __construct(
        private ChainDefinitionProviderInterface $chainProvider,
        private iterable $strategies,
    ) {
    }

    /**
     * Выполняет оркестрацию цепочки.
     */
    // phpcs:ignore PrikotovCodingStandard.Application.CommandHandlerReturnType.ForbiddenReturnType -- no-DB context, see coding-standard todo TASK-command-handler-return-type.md
    public function __invoke(OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $chainInfo = $this->chainProvider->loadChainInfo($command->chainName);
        $strategy = $this->resolveStrategy($chainInfo);

        return $command->resumeDir !== null
            ? $strategy->resume($chainInfo, $command)
            : $strategy->execute($chainInfo, $command);
    }

    /**
     * Определяет стратегию выполнения по идентификации цепочки.
     */
    private function resolveStrategy(ExecutionChainInfoVo $chainInfo): ExecutionStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($chainInfo)) {
                return $strategy;
            }
        }

        throw new LogicException(
            sprintf('No execution strategy found for chain "%s".', $chainInfo->name),
        );
    }
}
