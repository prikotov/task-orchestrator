<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\RunDynamicLoopServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopResultVo;

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
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?DynamicLoopAuditLoggerInterface $auditLogger = null,
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
     * @param \Closure(DynamicLoopConfigVo, DynamicLoopContextVo, int, string, string): void $callback
     */
    public function onExecute(\Closure $callback): self
    {
        $this->onExecuteCallback = $callback;

        return $this;
    }
}
