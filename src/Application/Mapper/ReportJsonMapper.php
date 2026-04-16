<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Application\Mapper;

use TasK\Orchestrator\Application\Mapper\ReportFormatMapperInterface;
use TasK\Orchestrator\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto;
use TasK\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TasK\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use Override;

use function array_map;
use function count;
use function json_encode;
use function round;

/**
 * Маппинг OrchestrateChainResultDto → machine-readable JSON.
 */
final readonly class ReportJsonMapper implements ReportFormatMapperInterface
{
    #[Override]
    public function map(OrchestrateChainResultDto $result, string $chainName, string $task): string
    {
        $data = [
            'chain' => $chainName,
            'task' => $task,
            'status' => $this->resolveStatus($result),
            'total_duration_ms' => (int)round($result->totalTime * 1000.0),
            'total_input_tokens' => $result->totalInputTokens,
            'total_output_tokens' => $result->totalOutputTokens,
            'total_cost' => round($result->totalCost, 6),
            'budget_exceeded' => $result->budgetExceeded,
            'budget_limit' => round($result->budgetLimit, 2),
            'budget_exceeded_role' => $result->budgetExceededRole,
            'total_iterations' => $result->totalIterations,
        ];

        $data = $this->appendSteps($data, $result);
        $data = $this->appendRounds($data, $result);
        $data = $this->appendSynthesis($data, $result);
        $data = $this->appendSessionDir($data, $result);

        /** @var string $json */
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $json;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function appendSteps(array $data, OrchestrateChainResultDto $result): array
    {
        if (count($result->stepResults) === 0) {
            return $data;
        }

        $data['steps'] = array_map(
            fn(int $idx, StepResultDto $step) => [
                'step' => $idx + 1,
                'role' => $step->role,
                'runner' => $step->runner,
                'status' => $this->resolveStepStatus($step),
                'input_tokens' => $step->inputTokens,
                'output_tokens' => $step->outputTokens,
                'cost' => round($step->cost, 6),
                'duration_ms' => (int)round($step->duration * 1000.0),
                'label' => $step->label,
                'fallback_runner' => $step->fallbackRunnerUsed,
                'iteration' => $step->iterationNumber,
                'iteration_warning' => $step->iterationWarning,
                'error_message' => $step->errorMessage,
            ],
            array_keys($result->stepResults),
            $result->stepResults,
        );

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function appendRounds(array $data, OrchestrateChainResultDto $result): array
    {
        if (count($result->roundResults) === 0) {
            return $data;
        }

        $data['rounds'] = array_map(
            fn(int $idx, DynamicRoundResultDto $round) => [
                'round' => $idx + 1,
                'role' => $round->role,
                'is_facilitator' => $round->isFacilitator,
                'status' => $round->isError ? 'error' : 'success',
                'input_tokens' => $round->inputTokens,
                'output_tokens' => $round->outputTokens,
                'cost' => round($round->cost, 6),
                'duration_ms' => (int)round($round->duration * 1000.0),
                'error_message' => $round->errorMessage,
            ],
            array_keys($result->roundResults),
            $result->roundResults,
        );

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function appendSynthesis(array $data, OrchestrateChainResultDto $result): array
    {
        if ($result->synthesis === null) {
            return $data;
        }

        $data['synthesis'] = $result->synthesis;
        $data['max_rounds_reached'] = $result->maxRoundsReached;

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function appendSessionDir(array $data, OrchestrateChainResultDto $result): array
    {
        if ($result->sessionDir === null) {
            return $data;
        }

        $data['session_dir'] = $result->sessionDir;

        return $data;
    }

    /**
     * Определяет статус отдельного шага.
     */
    private function resolveStepStatus(StepResultDto $step): string
    {
        if ($step->isError) {
            return 'error';
        }

        if ($step->role === 'quality_gate') {
            return $step->passed ? 'passed' : 'failed';
        }

        return 'success';
    }

    /**
     * Возвращает машинный статус цепочки: 'success' | 'partial' | 'budget_exceeded'.
     */
    private function resolveStatus(OrchestrateChainResultDto $result): string
    {
        if ($result->budgetExceeded) {
            return 'budget_exceeded';
        }

        foreach ($result->stepResults as $step) {
            if ($step->isError) {
                return 'partial';
            }
        }

        foreach ($result->roundResults as $round) {
            if ($round->isError) {
                return 'partial';
            }
        }

        return 'success';
    }
}
