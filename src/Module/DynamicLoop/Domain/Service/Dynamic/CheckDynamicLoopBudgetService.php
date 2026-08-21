<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\CheckDynamicLoopBudgetServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicBudgetCheckVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;

/**
 * Проверка и контроль бюджета dynamic-цикла.
 */
final readonly class CheckDynamicLoopBudgetService implements CheckDynamicLoopBudgetServiceInterface
{
    public function __construct(
        private \TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Budget\CheckDynamicLoopBudgetServiceInterface $budgetChecker,
        private DynamicLoopSessionLoggerInterface $sessionLogger,
    ) {
    }

    #[Override]
    public function checkAndApply(
        DynamicLoopExecution $execution,
        ?DynamicLoopBudgetVo $budget,
        string $role,
        float $stepCost,
    ): ?DynamicBudgetCheckVo {
        $budgetCheck = $this->budgetChecker->checkAfterTurn(
            $budget,
            $execution->getTotalCost(),
            $execution->getRoleCosts(),
            $role,
            $stepCost,
            $execution->isBudgetWarning80Logged(),
        );
        if ($budgetCheck === null) {
            return null;
        }

        if ($budgetCheck->warning80Triggered) {
            $execution->markBudgetWarning80Logged();
        }
        if ($budgetCheck->warningMessage !== '') {
            $execution->getJournal()->appendFacilitatorJournal($budgetCheck->warningMessage);
            $this->sessionLogger->writeContextFile(
                'facilitator_journal.md',
                $execution->getFacilitatorJournal(),
            );
        }

        return $budgetCheck;
    }
}
