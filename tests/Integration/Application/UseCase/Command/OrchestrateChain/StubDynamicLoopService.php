<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Dynamic\RunDynamicLoopServiceInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicLoopResultVo;

/**
 * Стаб RunDynamicLoopServiceInterface для integration-тестов.
 *
 * Вместо реального loop runner возвращает предзаданный результат.
 * Позволяет тестировать полный путь через все слои без внешних зависимостей.
 */
class StubDynamicLoopService implements RunDynamicLoopServiceInterface
{
    private ?DynamicLoopResultVo $result = null;

    private ?\Closure $onExecuteCallback = null;

    #[Override]
    public function execute(
        DynamicChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?AuditLoggerInterface $auditLogger = null,
    ): DynamicLoopResultVo {
        if ($this->onExecuteCallback !== null) {
            ($this->onExecuteCallback)($chain, $context, $startRound, $initialDiscussionHistory, $initialFacilitatorJournal);
        }

        if ($this->result === null) {
            throw new LogicException('StubDynamicLoopService: no result set. Call setResult() first.');
        }

        return $this->result;
    }

    public function setResult(DynamicLoopResultVo $result): self
    {
        $this->result = $result;

        return $this;
    }

    /**
     * Устанавливает callback, вызываемый при execute() для захвата параметров.
     *
     * @param \Closure(DynamicChainDefinitionVo, DynamicChainContextVo, int, string, string): void $callback
     */
    public function onExecute(\Closure $callback): self
    {
        $this->onExecuteCallback = $callback;

        return $this;
    }
}
