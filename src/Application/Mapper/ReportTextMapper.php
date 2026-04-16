<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Application\Mapper;

use TasK\Orchestrator\Application\Mapper\ReportFormatMapperInterface;
use TasK\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TasK\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use Override;

use function count;
use function round;

/**
 * Маппинг OrchestrateChainResultDto → CLI-friendly текстовый отчёт.
 */
final readonly class ReportTextMapper implements ReportFormatMapperInterface
{
    #[Override]
    public function map(OrchestrateChainResultDto $result, string $chainName, string $task): string
    {
        $separator = str_repeat('=', 60);
        $header = $this->buildHeader($chainName, $task, $separator);
        $summary = $this->buildSummary($result);
        $body = $this->buildBody($result);
        $footer = $this->buildFooter($result, $separator);

        return $header . $summary . $body . $footer;
    }

    /**
     * Строит заголовок отчёта.
     */
    private function buildHeader(string $chainName, string $task, string $separator): string
    {
        return $separator . "\n"
            . sprintf('Agent Chain Report: %s', $chainName) . "\n"
            . $separator . "\n"
            . sprintf('Task: %s', $task) . "\n";
    }

    /**
     * Строит блок сводных метрик.
     */
    private function buildSummary(OrchestrateChainResultDto $result): string
    {
        $lines = [];
        $lines[] = sprintf(
            'Total time: %s | Tokens: ↑%s ↓%s | Cost: $%.4f',
            $this->formatTime($result->totalTime),
            $this->formatTokens($result->totalInputTokens),
            $this->formatTokens($result->totalOutputTokens),
            $result->totalCost,
        );

        if ($result->budgetExceeded) {
            $lines[] = sprintf(
                '⚠ Budget exceeded: $%.4f of $%.2f limit (role: %s)',
                $result->totalCost,
                $result->budgetLimit,
                $result->budgetExceededRole ?? 'unknown',
            );
        }

        if ($result->totalIterations > 0) {
            $lines[] = sprintf('Iterations: %d', $result->totalIterations);
        }

        $lines[] = str_repeat('-', 60);

        return implode("\n", $lines) . "\n";
    }

    /**
     * Строит тело отчёта: шаги static-цепочки и/или раунды dynamic-цепочки.
     */
    private function buildBody(OrchestrateChainResultDto $result): string
    {
        $lines = [];

        if (count($result->stepResults) > 0) {
            $total = count($result->stepResults);
            foreach ($result->stepResults as $idx => $step) {
                $lines[] = ($step->role === 'quality_gate')
                    ? $this->formatQualityGateStep($idx + 1, $total, $step)
                    : $this->formatAgentStep($idx + 1, $total, $step);
            }
        }

        if (count($result->roundResults) > 0) {
            $total = count($result->roundResults);
            foreach ($result->roundResults as $idx => $round) {
                $duration = round($round->duration);
                $status = $round->isError ? '✗' : '✓';
                $roleLabel = $round->isFacilitator
                    ? sprintf('🎤 %s', $round->role)
                    : $round->role;

                $lines[] = sprintf(
                    '[Round %d/%d] %s ... %s (↑%s ↓%s $%.4f, %ds)',
                    $idx + 1,
                    $total,
                    $roleLabel,
                    $status,
                    $this->formatTokens($round->inputTokens),
                    $this->formatTokens($round->outputTokens),
                    $round->cost,
                    $duration,
                );
            }
        }

        if (count($lines) > 0) {
            return implode("\n", $lines) . "\n";
        }

        return '';
    }

    /**
     * Строит футер с итоговым статусом.
     */
    private function buildFooter(OrchestrateChainResultDto $result, string $separator): string
    {
        $status = $this->resolveStatusText($result);

        return str_repeat('-', 60) . "\n"
            . $status . "\n"
            . $separator . "\n";
    }

    private function formatQualityGateStep(int $num, int $total, StepResultDto $step): string
    {
        $gateStatus = $step->passed ? '✓' : '✗';
        $duration = round($step->duration);

        return sprintf(
            '[%d/%d] 🔍 %s: %s (%ds)',
            $num,
            $total,
            $step->label,
            $gateStatus,
            $duration,
        );
    }

    private function formatAgentStep(int $num, int $total, StepResultDto $step): string
    {
        $status = $step->isError ? '✗' : '✓';
        $duration = round($step->duration);
        $iterationSuffix = $step->iterationNumber !== null
            ? sprintf(' (iter %d)', $step->iterationNumber)
            : '';
        $fallbackSuffix = $step->fallbackRunnerUsed !== null
            ? sprintf(' → %s', $step->fallbackRunnerUsed)
            : '';

        return sprintf(
            '[%d/%d] %s @ %s%s%s ... %s (↑%s ↓%s $%.4f, %ds)',
            $num,
            $total,
            $step->role,
            $step->runner,
            $fallbackSuffix,
            $iterationSuffix,
            $status,
            $this->formatTokens($step->inputTokens),
            $this->formatTokens($step->outputTokens),
            $step->cost,
            $duration,
        );
    }

    /**
     * Определяет текст итогового статуса.
     */
    private function resolveStatusText(OrchestrateChainResultDto $result): string
    {
        $status = $this->resolveStatus($result);

        return match ($status) {
            'budget_exceeded' => 'Result: BUDGET_EXCEEDED | Chain interrupted',
            'partial' => 'Result: PARTIAL | Some steps failed',
            'success' => $result->synthesis !== null
                ? sprintf(
                    'Result: SUCCESS | Synthesis available%s',
                    $result->maxRoundsReached ? ' (max rounds reached)' : '',
                )
                : 'Result: SUCCESS | All steps completed',
            default => 'Result: ' . $status,
        };
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

    /**
     * Форматирует количество токенов (1500 → '1.5k').
     */
    private function formatTokens(int $tokens): string
    {
        if ($tokens >= 1000) {
            return sprintf('%.1fk', $tokens / 1000);
        }

        return (string)$tokens;
    }

    /**
     * Форматирует время в секундах/минутах.
     */
    private function formatTime(float $seconds): string
    {
        if ($seconds >= 60.0) {
            $mins = (int)($seconds / 60.0);
            $secs = $seconds - ((float)$mins * 60.0);

            return sprintf('%dm %.1fs', $mins, $secs);
        }

        return sprintf('%.1fs', $seconds);
    }
}
