<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use RuntimeException;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditDto;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;
use const FILE_APPEND;
use const LOCK_EX;

/**
 * JSONL audit-логгер для DynamicLoop.
 *
 * Реализует только DynamicLoopAuditLoggerInterface (Port своего модуля).
 * ChainExecution имеет собственную реализацию AuditLoggerInterface.
 */
final readonly class JsonlAuditLogger implements DynamicLoopAuditLoggerInterface
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    public function __construct(
        private string $logFilePath,
    ) {
    }

    // ─── DynamicLoopAuditLoggerInterface (DynamicLoop.Domain) ──────────

    #[Override]
    public function logChainStart(string $chainName, string $task): void
    {
        $this->append([
            'ts' => $this->timestamp(),
            'event' => 'chain_start',
            'chain' => $chainName,
            'task' => $task,
        ]);
    }

    #[Override]
    public function logStepStart(string $chainName, int $stepNumber, string $role, string $runner): void
    {
        $this->append([
            'ts' => $this->timestamp(),
            'event' => 'step_start',
            'chain' => $chainName,
            'step' => $stepNumber,
            'role' => $role,
            'runner' => $runner,
        ]);
    }

    #[Override]
    public function logDynamicStepResult(
        string $chainName,
        int $stepNumber,
        string $role,
        string $runner,
        DynamicRoundResultVo $result,
        float $durationMs,
    ): void {
        $record = [
            'ts' => $this->timestamp(),
            'event' => 'step_result',
            'chain' => $chainName,
            'step' => $stepNumber,
            'role' => $role,
            'runner' => $runner,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'cost' => $result->cost,
            'duration_ms' => round($durationMs, 1),
            'status' => $result->isError ? 'error' : 'success',
        ];

        if ($result->isError) {
            $record['error_message'] = $result->errorMessage ?? 'unknown';
        }

        $this->append($record);
    }

    #[Override]
    public function logDynamicChainResult(DynamicLoopAuditDto $audit): void
    {
        $hasErrors = false;
        foreach ($audit->stepStatuses as $status) {
            if ($status->isError) {
                $hasErrors = true;
                break;
            }
        }

        $this->append([
            'ts' => $this->timestamp(),
            'event' => 'chain_result',
            'chain' => $audit->chainName,
            'total_duration_ms' => round($audit->totalDurationMs, 1),
            'total_input_tokens' => $audit->totalInputTokens,
            'total_output_tokens' => $audit->totalOutputTokens,
            'total_cost' => $audit->totalCost,
            'status' => $hasErrors ? 'error' : 'success',
            'steps_count' => $audit->stepsCount,
            'budget_exceeded' => $audit->budgetExceeded,
        ]);
    }

    // ─── Private helpers ────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    private function append(array $data): void
    {
        $dir = dirname($this->logFilePath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(
                sprintf('Unable to create audit log directory: %s', $dir),
            );
        }

        /** @var non-empty-string $json */
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $bytes = file_put_contents($this->logFilePath, $json . "\n", FILE_APPEND | LOCK_EX);

        if ($bytes === false) {
            throw new RuntimeException(
                sprintf('Unable to write audit log to: %s', $this->logFilePath),
            );
        }
    }

    private function timestamp(): string
    {
        return (new DateTimeImmutable(timezone: new DateTimeZone('UTC')))
            ->format(self::DATE_FORMAT);
    }
}
