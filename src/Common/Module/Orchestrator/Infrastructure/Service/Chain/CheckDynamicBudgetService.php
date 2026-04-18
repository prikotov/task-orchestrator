<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Budget\CheckDynamicBudgetServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicBudgetCheckVo;
use Override;
use Psr\Log\LoggerInterface;

/**
 * Проверяет бюджет после раунда dynamic-цикла.
 */
final readonly class CheckDynamicBudgetService implements CheckDynamicBudgetServiceInterface
{
    public function __construct(
        private ChainSessionLoggerInterface $sessionLogger,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function checkAfterTurn(
        ?BudgetVo $budget,
        float $totalCost,
        array $roleCosts,
        string $role,
        float $stepCost,
        bool $warning80Logged,
    ): ?DynamicBudgetCheckVo {
        if ($budget === null) {
            return null;
        }

        $roleSpent = ($roleCosts[$role] ?? 0.0) + $stepCost;

        if (!$budget->isWithinStepBudget($stepCost)) {
            return $this->budgetExceeded($budget->getMaxCostPerStep() ?? 0.0, $role);
        }
        if (!$budget->isWithinTotalBudget($totalCost)) {
            return $this->budgetExceeded($budget->getMaxCostTotal() ?? 0.0, null);
        }
        if (!$budget->isWithinRoleStepBudget($role, $stepCost)) {
            return $this->budgetExceeded($budget->getRoleBudget($role)?->getMaxCostPerStep() ?? 0.0, $role);
        }
        if (!$budget->isWithinRoleBudget($role, $roleSpent)) {
            return $this->budgetExceeded($budget->getRoleBudget($role)?->getMaxCostTotal() ?? 0.0, $role);
        }

        if (!$warning80Logged && $budget->isNearTotalBudget($totalCost)) {
            $this->logger?->warning(sprintf(
                '[DynamicLoopRunner] 80%% budget warning: spent $%.4f of $%.2f.',
                $totalCost,
                $budget->getMaxCostTotal() ?? 0.0,
            ));

            return new DynamicBudgetCheckVo(
                warningMessage: sprintf(
                    "\n[WARNING] 80%% budget reached: spent $%.4f of $%.2f\n",
                    $totalCost,
                    $budget->getMaxCostTotal() ?? 0.0,
                ),
                warning80Triggered: true,
            );
        }

        return null;
    }

    private function budgetExceeded(float $limit, ?string $role): DynamicBudgetCheckVo
    {
        $this->sessionLogger->interruptSession('budget_exceeded');

        return new DynamicBudgetCheckVo(
            shouldBreak: true,
            budgetExceeded: true,
            budgetLimit: $limit,
            budgetExceededRole: $role,
        );
    }
}
