<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\Mapper;

use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Mapper\ReportFormatMapperInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\ParticipantMetricsDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\StepResultDto;






/**
 * Маппинг OrchestrateChainResultDto → machine-readable JSON.
 *
 * Для dynamic-цепочек добавляет participants — агрегированные по ролям
 * метрики участников (модель, выступления, время, токены, кэш, стоимость).
 */
final readonly class ReportJsonMapper implements ReportFormatMapperInterface
{
    public function __construct(
        private readonly ParticipantsMetricsMapper $participantsMetricsMapper,
    ) {
    }

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
        $data = $this->appendParticipants($data, $result);
        $data = $this->appendSynthesis($data, $result);
        $data = $this->appendSessionDir($data, $result);

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
                'output_text' => $step->outputText,
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
                'model' => $round->model,
                'provider' => $round->provider,
                'thinking' => $round->thinking,
                'cache_read_tokens' => $round->cacheReadTokens,
                'cache_write_tokens' => $round->cacheWriteTokens,
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
    private function appendParticipants(array $data, OrchestrateChainResultDto $result): array
    {
        $metrics = $this->participantsMetricsMapper->aggregate($result);
        if (count($metrics) === 0) {
            return $data;
        }

        $data['participants'] = array_map(
            static fn(ParticipantMetricsDto $participant): array => [
                'role' => $participant->role,
                'model' => $participant->model,
                'provider' => $participant->provider,
                'thinking' => $participant->thinking,
                'speeches' => $participant->speeches,
                'facilitator_steps' => $participant->facilitatorSteps,
                'total_duration_ms' => (int)round($participant->totalDuration * 1000.0),
                'input_tokens' => $participant->inputTokens,
                'output_tokens' => $participant->outputTokens,
                'cache_read_tokens' => $participant->cacheReadTokens,
                'cache_hit_rate' => $participant->cacheHitRate === null
                    ? null
                    : round($participant->cacheHitRate, 4),
                'cost' => round($participant->cost, 6),
            ],
            $metrics,
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
