<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Static;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\StaticChainExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use Override;
use Psr\Log\LoggerInterface;

use function sprintf;

/**
 * Проверка бюджетных ограничений static-цепочки.
 */
final readonly class CheckStaticBudgetService implements CheckStaticBudgetServiceInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Проверяет бюджет перед выполнением шага.
     *
     * @return bool true — нужно прервать цепочку
     */
    #[Override]
    public function shouldBreakBeforeStep(
        StaticChainExecution $execution,
        ?BudgetVo $budget,
        string $budgetRole,
    ): bool {
        $check = $execution->checkBudgetBeforeStep($budget, $budgetRole);
        if ($check !== null) {
            $execution->markBudgetExceeded($check['limit'], $check['role']);
            $this->logBudgetExceeded('before', $budgetRole, $execution->getTotalCost(), 0.0, $check);

            return true;
        }

        return false;
    }

    /**
     * Проверяет бюджет после выполнения шага.
     *
     * @return bool true — нужно прервать цепочку
     */
    #[Override]
    public function shouldBreakAfterStep(
        StaticChainExecution $execution,
        ?BudgetVo $budget,
        string $budgetRole,
        float $stepCost,
    ): bool {
        if ($execution->isNearTotalBudget($budget)) {
            $execution->markBudgetWarning80Logged();
            $this->logger?->warning(sprintf(
                '[StaticChainExecutor] 80%% budget warning: spent $%.4f of $%.2f.',
                $execution->getTotalCost(),
                $budget?->getMaxCostTotal() ?? 0.0,
            ));
        }

        $check = $execution->checkBudgetAfterStep($budget, $budgetRole, $stepCost);
        if ($check !== null) {
            $execution->markBudgetExceeded($check['limit'], $check['role']);
            $this->logBudgetExceeded('after', $budgetRole, $execution->getTotalCost(), $stepCost, $check);

            return true;
        }

        return false;
    }

    /** @param array{limit: float, role: string|null} $info */
    private function logBudgetExceeded(string $phase, string $role, float $totalCost, float $stepCost, array $info): void
    {
        if ($phase === 'before') {
            $this->logger?->warning(sprintf(
                $info['role'] === null
                    ? '[StaticChainExecutor] Budget exceeded before step "%s": spent $%.4f, limit $%.2f.'
                    : '[StaticChainExecutor] Role budget exceeded before step "%s": spent $%.4f, limit $%.2f.',
                $role,
                $totalCost,
                $info['limit'],
            ));

            return;
        }
        $this->logger?->warning(sprintf(
            $info['role'] === null
                ? '[StaticChainExecutor] Budget exceeded after step "%s": spent $%.4f, limit $%.2f.'
                : '[StaticChainExecutor] Role step budget exceeded for "%s": step cost $%.4f, limit $%.2f.',
            $role,
            $info['role'] === null ? $totalCost : $stepCost,
            $info['limit'],
        ));
    }
}
