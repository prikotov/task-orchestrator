<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use Override;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ToolStepRunnerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StepContextVo;

/**
 * Сервис выполнения tool-шага static-цепочки.
 *
 * Делегирует выполнение ToolStepRunnerInterface.
 * Без runner'а шаг считается успешным (no-op).
 */
final readonly class ExecuteToolStepService implements ExecuteStepServiceInterface
{
    private const string RUNNER_NAME = 'shell';

    public function __construct(
        private ?ToolStepRunnerInterface $toolStepRunner = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function supports(ChainStepTypeEnum $type): bool
    {
        return $type === ChainStepTypeEnum::tool;
    }

    #[Override]
    public function run(ExecutionStepVo $step, StepContextVo $context): StaticStepResultVo
    {
        if ($this->toolStepRunner === null) {
            return new StaticStepResultVo(
                role: 'tool',
                runner: self::RUNNER_NAME,
                outputText: '',
                inputTokens: 0,
                outputTokens: 0,
                cost: 0.0,
                duration: 0.0,
                isError: false,
                label: $step->getLabel(),
                exitCode: 0,
            );
        }

        $result = $this->toolStepRunner->run($step->toToolStepVo());
        $duration = $result->durationMs / 1000.0;

        if (!$result->success) {
            $this->logger?->warning(
                sprintf(
                    '[StaticChainExecutor] Tool step "%s" failed (exit code %d): %s',
                    $step->getLabel(),
                    $result->exitCode,
                    $result->stdout,
                ),
            );
        }

        return new StaticStepResultVo(
            role: 'tool',
            runner: self::RUNNER_NAME,
            outputText: $result->stdout,
            inputTokens: 0,
            outputTokens: 0,
            cost: 0.0,
            duration: $duration,
            isError: !$result->success,
            errorMessage: !$result->success
                ? sprintf('Tool "%s" failed with exit code %d', $step->getLabel(), $result->exitCode)
                : null,
            label: $step->getLabel(),
            exitCode: $result->exitCode,
            outputKey: $step->getOutputKey(),
        );
    }
}
