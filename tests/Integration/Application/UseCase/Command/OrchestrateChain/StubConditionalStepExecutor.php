<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\ExecuteConditionalStepServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ConditionalStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRoleConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;

/**
 * Stub ExecuteConditionalStepServiceInterface для conditional integration-тестов.
 *
 * PHPUnit не может mock final класс ConditionalStepResultVo,
 * поэтому используем реальный stub вместо createMock().
 *
 * Имитирует поведение: quality gate шаги проверяют exit code из конфигурации.
 * Если command содержит "exit 1" — step не passed.
 */
final class StubConditionalStepExecutor implements ExecuteConditionalStepServiceInterface
{
    private ?ConditionalStepResultVo $result = null;

    #[Override]
    public function executeStep(
        ExecutionStepVo $step,
        string $task,
        ?string $workingDir,
        int $timeout,
        ?string $previousContext,
        ?ExecutionRoleConfigVo $roleConfig,
        bool $noContextFiles,
    ): ConditionalStepResultVo {
        $isQualityGate = $step->isQualityGate();
        $role = $isQualityGate ? 'quality_gate' : ($step->getRole() ?? 'stub-role');
        $runner = $isQualityGate ? 'shell' : ($step->getRunner() ?? 'stub-runner');

        // Если установлен кастомный результат — вернуть его с корректной ролью
        if ($this->result !== null) {
            return new ConditionalStepResultVo(
                role: $role,
                runner: $runner,
                outputText: $this->result->outputText,
                inputTokens: $this->result->inputTokens,
                outputTokens: $this->result->outputTokens,
                cost: $this->result->cost,
                duration: $this->result->duration,
                isError: $this->result->isError,
                errorMessage: $this->result->errorMessage,
                passed: $this->result->passed,
                exitCode: $this->result->exitCode,
                label: $step->getLabel(),
                timedOut: $this->result->timedOut,
            );
        }

        // Quality gate: парсим exit code из command
        $passed = true;
        $exitCode = 0;
        if ($isQualityGate) {
            $command = $step->getCommand();
            if (str_contains($command, 'exit 1')) {
                $passed = false;
                $exitCode = 1;
            }
        }

        return new ConditionalStepResultVo(
            role: $role,
            runner: $runner,
            outputText: $isQualityGate ? '' : 'Stub step output',
            inputTokens: $isQualityGate ? 0 : 50,
            outputTokens: $isQualityGate ? 0 : 100,
            cost: $isQualityGate ? 0.0 : 0.01,
            duration: 0.5,
            isError: false,
            passed: $passed,
            exitCode: $exitCode,
            label: $step->getLabel(),
        );
    }

    public function setResult(ConditionalStepResultVo $result): self
    {
        $this->result = $result;

        return $this;
    }
}
