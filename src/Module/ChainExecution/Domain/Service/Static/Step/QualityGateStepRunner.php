<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step;

use Override;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\QualityGateRunnerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StepContextVo;

/**
 * Стратегия выполнения quality-gate шага static-цепочки.
 *
 * Делегирует выполнение QualityGateRunnerInterface.
 * Без runner'а шаг считается успешным (no-op).
 */
final readonly class QualityGateStepRunner implements StepRunnerInterface
{
    private const string RUNNER_NAME = 'shell';

    public function __construct(
        private ?QualityGateRunnerInterface $qualityGateRunner = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function supports(ChainStepTypeEnum $type): bool
    {
        return $type === ChainStepTypeEnum::qualityGate;
    }

    #[Override]
    public function run(ExecutionStepVo $step, StepContextVo $context): StaticStepResultVo
    {
        if ($this->qualityGateRunner === null) {
            return new StaticStepResultVo(
                role: 'quality_gate',
                runner: self::RUNNER_NAME,
                outputText: '',
                inputTokens: 0,
                outputTokens: 0,
                cost: 0.0,
                duration: 0.0,
                isError: false,
                label: $step->getLabel(),
                passed: true,
            );
        }

        $result = $this->qualityGateRunner->run($step->toQualityGateVo());
        $duration = $result->durationMs / 1000.0;

        if (!$result->passed) {
            $this->logger?->warning(
                sprintf(
                    '[StaticChainExecutor] Quality gate "%s" failed (exit code %d): %s',
                    $result->label,
                    $result->exitCode,
                    $result->output,
                ),
            );
        }

        return new StaticStepResultVo(
            role: 'quality_gate',
            runner: self::RUNNER_NAME,
            outputText: $result->output,
            inputTokens: 0,
            outputTokens: 0,
            cost: 0.0,
            duration: $duration,
            isError: false,
            label: $result->label,
            passed: $result->passed,
            exitCode: $result->exitCode,
        );
    }
}
