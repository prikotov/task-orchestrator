<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;

/**
 * Стратегия выполнения static-цепочки.
 *
 * Делегирует линейное выполнение шагов в ExecuteStaticChainServiceInterface.
 * Resume не поддерживается — брошено LogicException.
 */
final readonly class StaticExecutionStrategy implements ExecutionStrategyInterface
{
    /** @var int Дефолтный таймаут (секунды) для static-цепочки при отсутствии CLI timeout */
    private const int DEFAULT_STATIC_TIMEOUT = 300;

    public function __construct(
        private ExecuteStaticChainServiceInterface $staticChainExecutor,
    ) {
    }

    #[Override]
    public function execute(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        return $this->staticChainExecutor->execute(
            $chain,
            $command->task,
            $command->workingDir,
            $command->timeout ?? self::DEFAULT_STATIC_TIMEOUT,
            null, // static chains have no session-scoped audit log
            $command->noContextFiles,
        );
    }

    #[Override]
    public function resume(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        throw new LogicException('Static chain does not support resume.');
    }

    #[Override]
    public function supports(ChainDefinitionVo $chain): bool
    {
        return $chain->getType() === ChainTypeEnum::staticType;
    }
}
