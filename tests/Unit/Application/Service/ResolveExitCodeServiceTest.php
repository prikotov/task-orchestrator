<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Enum\OrchestrateExitCodeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\StepResultDto;

#[CoversClass(OrchestrateChainResultDto::class)]
final class ResolveExitCodeServiceTest extends TestCase
{
    // ─── resolveExitCode: static chain ─────────────────────────────────────

    #[Test]
    public function staticSuccessReturnsSuccess(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [],
            budgetExceeded: false,
        );

        $exitCode = $result->resolveExitCode(false);

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

        $exitCode = $result->resolveExitCode(false);

        self::assertSame(OrchestrateExitCodeEnum::chainFailed, $exitCode);
    }

    // ─── resolveExitCode: dynamic chain ────────────────────────────────────

    #[Test]
    public function dynamicChainWithSynthesisReturnsSuccess(): void
    {
        $result = new OrchestrateChainResultDto(
            synthesis: 'Done.',
            budgetExceeded: false,
        );

        $exitCode = $result->resolveExitCode(true);

        self::assertSame(OrchestrateExitCodeEnum::success, $exitCode);
    }

    #[Test]
    public function dynamicChainWithoutSynthesisReturnsChainFailed(): void
    {
        $result = new OrchestrateChainResultDto(
            synthesis: null,
            budgetExceeded: false,
        );

        $exitCode = $result->resolveExitCode(true);

        self::assertSame(OrchestrateExitCodeEnum::chainFailed, $exitCode);
    }

    // ─── resolveExitCode: budget priority ───────────────────────────────────

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

        $exitCode = $result->resolveExitCode(false);

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

        $exitCode = $result->resolveExitCode(false);

        self::assertSame(OrchestrateExitCodeEnum::budgetExceeded, $exitCode);
    }

    // ─── isSuccessful ──────────────────────────────────────────────────────

    #[Test]
    public function isSuccessfulReturnsTrueForStaticSuccess(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [],
            budgetExceeded: false,
        );

        self::assertTrue($result->isSuccessful(false));
    }

    #[Test]
    public function isSuccessfulReturnsFalseForStaticFailure(): void
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

        self::assertFalse($result->isSuccessful(false));
    }

    #[Test]
    public function isSuccessfulReturnsTrueForDynamicWithSynthesis(): void
    {
        $result = new OrchestrateChainResultDto(
            synthesis: 'Done.',
            budgetExceeded: false,
        );

        self::assertTrue($result->isSuccessful(true));
    }

    #[Test]
    public function isSuccessfulReturnsFalseForBudgetExceeded(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [],
            budgetExceeded: true,
            budgetLimit: 10.0,
            totalCost: 12.0,
        );

        self::assertFalse($result->isSuccessful(false));
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

        $exitCode = $result->resolveExitCode(true);

        self::assertSame(OrchestrateExitCodeEnum::budgetExceeded, $exitCode);
    }

    // ─── resolveExitCode: timeout ───────────────────────────────────────────

    #[Test]
    public function staticChainWithTimedOutStepReturnsTimeout(): void
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
                    duration: 30.0,
                    isError: true,
                    errorMessage: 'Agent timed out after 30 seconds.',
                    timedOut: true,
                ),
            ],
            budgetExceeded: false,
            timedOut: true,
        );

        $exitCode = $result->resolveExitCode(false);

        self::assertSame(OrchestrateExitCodeEnum::timeout, $exitCode);
    }

    #[Test]
    public function dynamicChainTimedOutReturnsTimeout(): void
    {
        $result = new OrchestrateChainResultDto(
            synthesis: null,
            budgetExceeded: false,
            timedOut: true,
        );

        $exitCode = $result->resolveExitCode(true);

        self::assertSame(OrchestrateExitCodeEnum::timeout, $exitCode);
    }

    #[Test]
    public function budgetExceededTakesPriorityOverTimeout(): void
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
                    duration: 30.0,
                    isError: true,
                    errorMessage: 'Agent timed out',
                    timedOut: true,
                ),
            ],
            budgetExceeded: true,
            budgetLimit: 5.0,
            totalCost: 6.0,
            timedOut: true,
        );

        $exitCode = $result->resolveExitCode(false);

        self::assertSame(OrchestrateExitCodeEnum::budgetExceeded, $exitCode);
    }

    #[Test]
    public function timeoutTakesPriorityOverGenericError(): void
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
                    duration: 30.0,
                    isError: true,
                    errorMessage: 'Agent timed out after 30 seconds.',
                    timedOut: true,
                ),
            ],
            budgetExceeded: false,
            timedOut: true,
        );

        $exitCode = $result->resolveExitCode(false);

        self::assertSame(OrchestrateExitCodeEnum::timeout, $exitCode);
    }

    #[Test]
    public function isSuccessfulReturnsFalseForTimedOut(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [],
            budgetExceeded: false,
            timedOut: true,
        );

        self::assertFalse($result->isSuccessful(false));
    }
}
