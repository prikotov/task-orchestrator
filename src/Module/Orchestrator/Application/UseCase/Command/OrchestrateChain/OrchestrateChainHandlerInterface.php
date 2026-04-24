<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain;

/**
 * Интерфейс обработчика команды оркестрации цепочки.
 *
 * Позволяет Presentation-слою зависеть от абстракции (DIP),
 * а не от конкретного final readonly handler'а.
 */
interface OrchestrateChainHandlerInterface
{
    public function __invoke(OrchestrateChainCommand $command): OrchestrateChainResultDto;
}
