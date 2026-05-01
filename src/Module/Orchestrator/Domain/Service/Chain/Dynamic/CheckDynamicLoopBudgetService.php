<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Budget\CheckDynamicBudgetServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicBudgetCheckVo;

/**
 * Проверка и контроль бюджета dynamic-цикла.
 *
 * Оборачивает CheckDynamicBudgetServiceInterface: после каждого turn'а
 * проверяет бюджет, применяет предупреждения к execution entity,
 * записывает сообщения в журнал сессии.
 */
final readonly class CheckDynamicLoopBudgetService implements CheckDynamicLoopBudgetServiceInterface
{
    public function __construct(
        private CheckDynamicBudgetServiceInterface $budgetChecker,
        private ChainSessionLoggerInterface $sessionLogger,
    ) {
    }

    /**
     * Проверяет бюджет и применяет результат к execution entity.
     *
     * Возвращает DynamicBudgetCheckVo если есть результат проверки, null если бюджет не задан.
     * Побочный эффект: обновляет execution (warning80 флаг, journal) и записывает в файл.
     */
    #[Override]
    public function checkAndApply(
        DynamicLoopExecution $execution,
        ?BudgetVo $budget,
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
            $execution->appendFacilitatorJournal($budgetCheck->warningMessage);
            $this->sessionLogger->writeContextFile(
                'facilitator_journal.md',
                $execution->getFacilitatorJournal(),
            );
        }

        return $budgetCheck;
    }
}
