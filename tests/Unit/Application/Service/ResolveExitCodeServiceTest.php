<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Enum\OrchestrateExitCodeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\ResolveExitCodeService;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\ChainNotFoundException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\RoleNotFoundException;

#[CoversClass(ResolveExitCodeService::class)]
final class ResolveExitCodeServiceTest extends TestCase
{
    private ResolveExitCodeService $service;

    #[Override]
    protected function setUp(): void
    {
        $this->service = new ResolveExitCodeService();
    }

    // ─── resolveFromThrowable ─────────────────────────────────────────────────

    #[Test]
    public function chainNotFoundExceptionMapsToChainNotFound(): void
    {
        $result = $this->service->resolveFromThrowable(new ChainNotFoundException('missing'));

        self::assertSame(OrchestrateExitCodeEnum::chainNotFound, $result);
    }

    #[Test]
    public function roleNotFoundExceptionMapsToInvalidConfig(): void
    {
        $result = $this->service->resolveFromThrowable(new RoleNotFoundException('bad_role'));

        self::assertSame(OrchestrateExitCodeEnum::invalidConfig, $result);
    }

    #[Test]
    public function genericExceptionMapsToChainFailed(): void
    {
        $result = $this->service->resolveFromThrowable(new \RuntimeException('something broke'));

        self::assertSame(OrchestrateExitCodeEnum::chainFailed, $result);
    }

    #[Test]
    public function domainExceptionMapsToChainFailed(): void
    {
        $result = $this->service->resolveFromThrowable(new \DomainException('domain error'));

        self::assertSame(OrchestrateExitCodeEnum::chainFailed, $result);
    }

    // ─── resolveFromResult: static chain ─────────────────────────────────────

    #[Test]
    public function staticSuccessReturnsSuccess(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [],
            budgetExceeded: false,
        );

        $exitCode = $this->service->resolveFromResult($result, false);

        self::assertSame(OrchestrateExitCodeEnum::success, $exitCode);
    }

    #[Test]
    public function staticChainWithErrorStepReturnsChainFailed(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'agent',
                    runner: 'pi',
                    outputText: '',
                    inputTokens: 0,
                    outputTokens: 0,
                    cost: 0.0,
                    duration: 1.0,
                    isError: true,
                    errorMessage: 'Agent crashed',
                ),
            ],
            budgetExceeded: false,
        );

        $exitCode = $this->service->resolveFromResult($result, false);

        self::assertSame(OrchestrateExitCodeEnum::chainFailed, $exitCode);
    }

    // ─── resolveFromResult: dynamic chain ────────────────────────────────────

    #[Test]
    public function dynamicChainWithSynthesisReturnsSuccess(): void
    {
        $result = new OrchestrateChainResultDto(
            synthesis: 'Done.',
            budgetExceeded: false,
        );

        $exitCode = $this->service->resolveFromResult($result, true);

        self::assertSame(OrchestrateExitCodeEnum::success, $exitCode);
    }

    #[Test]
    public function dynamicChainWithoutSynthesisReturnsChainFailed(): void
    {
        $result = new OrchestrateChainResultDto(
            synthesis: null,
            budgetExceeded: false,
        );

        $exitCode = $this->service->resolveFromResult($result, true);

        self::assertSame(OrchestrateExitCodeEnum::chainFailed, $exitCode);
    }

    // ─── resolveFromResult: budget priority ───────────────────────────────────

    #[Test]
    public function budgetExceededTakesPriorityOverStepError(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'agent',
                    runner: 'pi',
                    outputText: '',
                    inputTokens: 0,
                    outputTokens: 0,
                    cost: 0.0,
                    duration: 1.0,
                    isError: true,
                    errorMessage: 'Agent crashed',
                ),
            ],
            budgetExceeded: true,
            budgetLimit: 5.0,
            totalCost: 6.0,
        );

        $exitCode = $this->service->resolveFromResult($result, false);

        self::assertSame(OrchestrateExitCodeEnum::budgetExceeded, $exitCode);
    }

    #[Test]
    public function budgetExceededReturnsBudgetExceededCode(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [],
            budgetExceeded: true,
            budgetLimit: 10.0,
            totalCost: 12.0,
        );

        $exitCode = $this->service->resolveFromResult($result, false);

        self::assertSame(OrchestrateExitCodeEnum::budgetExceeded, $exitCode);
    }

    #[Test]
    public function budgetExceededTakesPriorityOverDynamicFailure(): void
    {
        $result = new OrchestrateChainResultDto(
            synthesis: null,
            budgetExceeded: true,
            budgetLimit: 10.0,
            totalCost: 12.0,
        );

        $exitCode = $this->service->resolveFromResult($result, true);

        self::assertSame(OrchestrateExitCodeEnum::budgetExceeded, $exitCode);
    }
}
