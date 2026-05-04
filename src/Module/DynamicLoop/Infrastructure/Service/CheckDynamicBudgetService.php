<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service;

use Override;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Budget\CheckDynamicLoopBudgetServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionWriterInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicBudgetCheckVo;

/**
 * Проверяет бюджет после раунда dynamic-цикла.
 */
final readonly class CheckDynamicBudgetService implements CheckDynamicLoopBudgetServiceInterface
{
    public function __construct(
        private DynamicLoopSessionWriterInterface $sessionWriter,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function checkAfterTurn(
        ?DynamicLoopBudgetVo $budget,
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
        $this->sessionWriter->interruptSession('budget_exceeded');

        return new DynamicBudgetCheckVo(
            shouldBreak: true,
            budgetExceeded: true,
            budgetLimit: $limit,
            budgetExceededRole: $role,
        );
    }
}
