<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Integration\Service\Audit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Dto\ChainResultAuditDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Dto\StepAuditStatusDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticChainAuditVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepAuditVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\Audit\StaticAuditService;

/**
 * Unit-тест StaticAuditService: проверяет маппинг StaticExecution VO → Orchestrator DTO.
 */
#[CoversClass(StaticAuditService::class)]
final class StaticAuditServiceTest extends TestCase
{
    private AuditLoggerInterface $auditLogger;

    private StaticAuditService $service;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLoggerInterface::class);
        $this->service = new StaticAuditService($this->auditLogger);
    }

    // --- logChainStart ---

    #[Test]
    public function logChainStartDelegatesToAuditLogger(): void
    {
        $this->auditLogger->expects($this->once())
            ->method('logChainStart')
            ->with('test-chain', 'do work');

        $this->service->logChainStart('test-chain', 'do work');
    }

    // --- logStepStart ---

    #[Test]
    public function logStepStartDelegatesToAuditLogger(): void
    {
        $this->auditLogger->expects($this->once())
            ->method('logStepStart')
            ->with('test-chain', 1, 'analyst', 'pi');

        $this->service->logStepStart('test-chain', 1, 'analyst', 'pi');
    }

    // --- logStepResult (success) ---

    #[Test]
    public function logStepResultMapsSuccessToChainRunResult(): void
    {
        $stepResult = new StaticStepResultVo(
            role: 'analyst',
            runner: 'pi',
            outputText: 'analysis done',
            inputTokens: 100,
            outputTokens: 200,
            cost: 0.01,
            duration: 1.5,
            isError: false,
        );

        $this->auditLogger->expects($this->once())
            ->method('logStepResult')
            ->with(
                'test-chain',
                1,
                'analyst',
                'pi',
                $this->callback(static function (ChainRunResultVo $result): bool {
                    return !$result->isError()
                        && $result->getOutputText() === 'analysis done'
                        && $result->getInputTokens() === 100
                        && $result->getOutputTokens() === 200
                        && $result->getCost() === 0.01;
                }),
                1500.0,
            );

        $this->service->logStepResult('test-chain', 1, 'analyst', 'pi', $stepResult, 1500.0);
    }

    // --- logStepResult (error) ---

    #[Test]
    public function logStepResultMapsErrorToChainRunResult(): void
    {
        $stepResult = new StaticStepResultVo(
            role: 'developer',
            runner: 'pi',
            outputText: '',
            inputTokens: 50,
            outputTokens: 0,
            cost: 0.005,
            duration: 2.0,
            isError: true,
            errorMessage: 'timeout exceeded',
            timedOut: true,
        );

        $this->auditLogger->expects($this->once())
            ->method('logStepResult')
            ->with(
                'test-chain',
                2,
                'developer',
                'pi',
                $this->callback(static function (ChainRunResultVo $result): bool {
                    return $result->isError()
                        && $result->getErrorMessage() === 'timeout exceeded'
                        && $result->isTimedOut();
                }),
                2000.0,
            );

        $this->service->logStepResult('test-chain', 2, 'developer', 'pi', $stepResult, 2000.0);
    }

    // --- logChainResult ---

    #[Test]
    public function logChainResultMapsAuditVoToDto(): void
    {
        $audit = new StaticChainAuditVo(
            chainName: 'test-chain',
            totalDurationMs: 5000.0,
            totalInputTokens: 300,
            totalOutputTokens: 600,
            totalCost: 0.05,
            budgetExceeded: false,
            stepsCount: 2,
            stepStatuses: [
                new StaticStepAuditVo(isError: false),
                new StaticStepAuditVo(isError: true),
            ],
        );

        $this->auditLogger->expects($this->once())
            ->method('logChainResult')
            ->with($this->callback(static function (ChainResultAuditDto $dto): bool {
                return $dto->chainName === 'test-chain'
                    && $dto->totalDurationMs === 5000.0
                    && $dto->totalInputTokens === 300
                    && $dto->totalOutputTokens === 600
                    && $dto->totalCost === 0.05
                    && !$dto->budgetExceeded
                    && $dto->stepsCount === 2
                    && count($dto->stepStatuses) === 2
                    && !$dto->stepStatuses[0]->isError
                    && $dto->stepStatuses[1]->isError;
            }));

        $this->service->logChainResult($audit);
    }

    // --- logChainResult (budget exceeded) ---

    #[Test]
    public function logChainResultPreservesBudgetExceededFlag(): void
    {
        $audit = new StaticChainAuditVo(
            chainName: 'budget-chain',
            totalDurationMs: 10000.0,
            totalInputTokens: 1000,
            totalOutputTokens: 2000,
            totalCost: 1.5,
            budgetExceeded: true,
            stepsCount: 5,
            stepStatuses: array_fill(0, 5, new StaticStepAuditVo(isError: false)),
        );

        $this->auditLogger->expects($this->once())
            ->method('logChainResult')
            ->with($this->callback(static function (ChainResultAuditDto $dto): bool {
                return $dto->budgetExceeded === true
                    && $dto->totalCost === 1.5
                    && count($dto->stepStatuses) === 5;
            }));

        $this->service->logChainResult($audit);
    }
}
