<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Service\Chain\Dynamic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Budget\CheckDynamicBudgetServiceInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Dynamic\CheckDynamicLoopBudgetService;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicBudgetCheckVo;

#[CoversClass(CheckDynamicLoopBudgetService::class)]
final class CheckDynamicLoopBudgetServiceTest extends TestCase
{
    private CheckDynamicBudgetServiceInterface $budgetChecker;
    private ChainSessionLoggerInterface $sessionLogger;
    private CheckDynamicLoopBudgetService $service;

    protected function setUp(): void
    {
        $this->budgetChecker = $this->createMock(CheckDynamicBudgetServiceInterface::class);
        $this->sessionLogger = $this->createMock(ChainSessionLoggerInterface::class);
        $this->service = new CheckDynamicLoopBudgetService(
            $this->budgetChecker,
            $this->sessionLogger,
        );
    }

    // ─── checkAndApply ────────────────────────────────────────────────

    #[Test]
    public function checkAndApplyReturnsNullWhenBudgetIsNull(): void
    {
        $execution = new DynamicLoopExecution();

        $this->budgetChecker->method('checkAfterTurn')->willReturn(null);

        $result = $this->service->checkAndApply($execution, null, 'role', 0.5);

        self::assertNull($result);
    }

    #[Test]
    public function checkAndApplyDelegatesToBudgetChecker(): void
    {
        $execution = new DynamicLoopExecution();
        $budget = new BudgetVo(maxCostTotal: 10.0);
        $budgetCheck = new DynamicBudgetCheckVo(
            shouldBreak: false,
            warning80Triggered: false,
        );

        $this->budgetChecker->expects(self::once())
            ->method('checkAfterTurn')
            ->with(
                $budget,
                0.0,
                [],
                'architect',
                0.5,
                false,
            )
            ->willReturn($budgetCheck);

        $result = $this->service->checkAndApply($execution, $budget, 'architect', 0.5);

        self::assertSame($budgetCheck, $result);
    }

    #[Test]
    public function checkAndApplyMarksWarning80WhenTriggered(): void
    {
        $execution = new DynamicLoopExecution();
        $budget = new BudgetVo(maxCostTotal: 10.0);
        $budgetCheck = new DynamicBudgetCheckVo(
            shouldBreak: false,
            warning80Triggered: true,
            warningMessage: '80% budget reached',
        );

        $this->budgetChecker->method('checkAfterTurn')->willReturn($budgetCheck);
        $this->sessionLogger->method('writeContextFile');

        $this->service->checkAndApply($execution, $budget, 'role', 8.0);

        self::assertTrue($execution->isBudgetWarning80Logged());
    }

    #[Test]
    public function checkAndApplyWritesJournalWhenWarningMessage(): void
    {
        $execution = new DynamicLoopExecution();
        $budget = new BudgetVo(maxCostTotal: 10.0);
        $budgetCheck = new DynamicBudgetCheckVo(
            shouldBreak: false,
            warningMessage: '80% budget warning',
        );

        $this->budgetChecker->method('checkAfterTurn')->willReturn($budgetCheck);
        $this->sessionLogger->expects(self::once())
            ->method('writeContextFile')
            ->with('facilitator_journal.md', self::stringContains('80% budget warning'));

        $this->service->checkAndApply($execution, $budget, 'role', 8.0);

        self::assertStringContainsString('80% budget warning', $execution->getFacilitatorJournal());
    }

    #[Test]
    public function checkAndApplyReturnsBreakResultWhenBudgetExceeded(): void
    {
        $execution = new DynamicLoopExecution();
        $budget = new BudgetVo(maxCostTotal: 10.0);
        $budgetCheck = new DynamicBudgetCheckVo(
            shouldBreak: true,
            budgetExceeded: true,
            budgetLimit: 10.0,
            budgetExceededRole: 'architect',
        );

        $this->budgetChecker->method('checkAfterTurn')->willReturn($budgetCheck);

        $result = $this->service->checkAndApply($execution, $budget, 'architect', 12.0);

        self::assertNotNull($result);
        self::assertTrue($result->shouldBreak);
        self::assertTrue($result->budgetExceeded);
        self::assertSame('architect', $result->budgetExceededRole);
    }

    #[Test]
    public function checkAndApplyDoesNotWriteWhenNoWarningMessage(): void
    {
        $execution = new DynamicLoopExecution();
        $budget = new BudgetVo(maxCostTotal: 10.0);
        $budgetCheck = new DynamicBudgetCheckVo(
            shouldBreak: false,
            warningMessage: '',
        );

        $this->budgetChecker->method('checkAfterTurn')->willReturn($budgetCheck);
        $this->sessionLogger->expects(self::never())->method('writeContextFile');

        $this->service->checkAndApply($execution, $budget, 'role', 5.0);
    }
}
