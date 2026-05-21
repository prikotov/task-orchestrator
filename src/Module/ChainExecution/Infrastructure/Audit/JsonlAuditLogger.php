<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Audit;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use RuntimeException;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Dto\ChainResultAuditDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use const FILE_APPEND;
use const LOCK_EX;

/**
 * JSONL audit-логгер для ChainExecution (static/conditional chains).
 *
 * Реализует AuditLoggerInterface из ChainExecution.Domain —
 * Infrastructure своего модуля, реализует Port своего Domain.
 *
 * @SuppressWarnings(PHPMD.ErrorControlOperator)
 */
final readonly class JsonlAuditLogger implements AuditLoggerInterface
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    public function __construct(
        private string $logFilePath,
    ) {
    }

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
    public function logStepResult(
        string $chainName,
        int $stepNumber,
        string $role,
        string $runner,
        ChainRunResultVo $result,
        float $durationMs,
    ): void {
        $record = [
            'ts' => $this->timestamp(),
            'event' => 'step_result',
            'chain' => $chainName,
            'step' => $stepNumber,
            'role' => $role,
            'runner' => $runner,
            'input_tokens' => $result->getInputTokens(),
            'output_tokens' => $result->getOutputTokens(),
            'cost' => $result->getCost(),
            'duration_ms' => round($durationMs, 1),
            'status' => $result->isError() ? 'error' : 'success',
        ];

        if ($result->isError()) {
            $record['error_message'] = $result->getErrorMessage() ?? 'unknown';
        }

        $this->append($record);
    }

    #[Override]
    public function logChainResult(ChainResultAuditDto $audit): void
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

    /**
     * @param array<string, mixed> $data
     */
    private function append(array $data): void
    {
        $dir = dirname($this->logFilePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
            if (!is_dir($dir)) {
                throw new RuntimeException(
                    sprintf('Unable to create audit log directory: %s', $dir),
                );
            }
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
