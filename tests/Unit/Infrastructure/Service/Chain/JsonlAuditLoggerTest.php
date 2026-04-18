<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Dto\ChainResultAuditDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Dto\StepAuditStatusDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain\JsonlAuditLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use const JSON_THROW_ON_ERROR;

#[CoversClass(JsonlAuditLogger::class)]
final class JsonlAuditLoggerTest extends TestCase
{
    private string $logFile;
    private string $logDir;
    private JsonlAuditLogger $logger;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/task_audit_test_' . uniqid();
        $this->logFile = $this->logDir . '/audit.jsonl';
        $this->logger = new JsonlAuditLogger($this->logFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        if (is_dir($this->logDir)) {
            @rmdir($this->logDir);
        }
    }

    #[Test]
    public function logChainStartCreatesFileAndWritesJsonl(): void
    {
        $this->logger->logChainStart('implement', 'Build feature X');

        $content = file_get_contents($this->logFile);
        self::assertNotFalse($content);

        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('chain_start', $record['event']);
        self::assertSame('implement', $record['chain']);
        self::assertSame('Build feature X', $record['task']);
        self::assertArrayHasKey('ts', $record);
    }

    #[Test]
    public function logStepStartWritesCorrectRecord(): void
    {
        $this->logger->logStepStart('implement', 1, 'analyst', 'pi');

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('step_start', $record['event']);
        self::assertSame('implement', $record['chain']);
        self::assertSame(1, $record['step']);
        self::assertSame('analyst', $record['role']);
        self::assertSame('pi', $record['runner']);
    }

    #[Test]
    public function logStepResultWritesSuccessRecord(): void
    {
        $result = ChainRunResultVo::createFromSuccess(
            outputText: 'Done',
            inputTokens: 1500,
            outputTokens: 800,
            cost: 0.023,
        );

        $this->logger->logStepResult('implement', 1, 'analyst', 'pi', $result, 5432.0);

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('step_result', $record['event']);
        self::assertSame('implement', $record['chain']);
        self::assertSame(1, $record['step']);
        self::assertSame('analyst', $record['role']);
        self::assertSame('pi', $record['runner']);
        self::assertSame(1500, $record['input_tokens']);
        self::assertSame(800, $record['output_tokens']);
        self::assertSame(0.023, $record['cost']);
        self::assertEquals(5432.0, $record['duration_ms']);
        self::assertSame('success', $record['status']);
        self::assertArrayNotHasKey('error_message', $record);
    }

    #[Test]
    public function logStepResultWritesErrorRecord(): void
    {
        $result = ChainRunResultVo::createFromError(
            errorMessage: 'Timeout exceeded',
            exitCode: 124,
        );

        $this->logger->logStepResult('implement', 2, 'developer', 'pi', $result, 300000.0);

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('step_result', $record['event']);
        self::assertSame('error', $record['status']);
        self::assertSame('Timeout exceeded', $record['error_message']);
    }

    #[Test]
    public function logChainResultWritesAggregatedRecord(): void
    {
        $this->logger->logChainResult(new ChainResultAuditDto(
            chainName: 'implement',
            totalDurationMs: 45200.0,
            totalInputTokens: 12500,
            totalOutputTokens: 8300,
            totalCost: 0.42,
            budgetExceeded: false,
            stepsCount: 4,
            stepStatuses: [],
        ));

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('chain_result', $record['event']);
        self::assertSame('implement', $record['chain']);
        self::assertEquals(45200.0, $record['total_duration_ms']);
        self::assertSame(12500, $record['total_input_tokens']);
        self::assertSame(8300, $record['total_output_tokens']);
        self::assertSame(0.42, $record['total_cost']);
        self::assertSame('success', $record['status']);
        self::assertFalse($record['budget_exceeded']);
    }

    #[Test]
    public function logChainResultDetectsErrorStatus(): void
    {
        $this->logger->logChainResult(new ChainResultAuditDto(
            chainName: 'implement',
            totalDurationMs: 1000.0,
            totalInputTokens: 100,
            totalOutputTokens: 50,
            totalCost: 0.01,
            budgetExceeded: false,
            stepsCount: 1,
            stepStatuses: [new StepAuditStatusDto(isError: true)],
        ));

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('error', $record['status']);
    }

    #[Test]
    public function multipleCallsAppendLines(): void
    {
        $this->logger->logChainStart('implement', 'Task A');
        $this->logger->logStepStart('implement', 1, 'analyst', 'pi');
        $result = ChainRunResultVo::createFromSuccess('Done', 100, 50, 0, 0, 0.01);
        $this->logger->logStepResult('implement', 1, 'analyst', 'pi', $result, 5000.0);

        $content = file_get_contents($this->logFile);
        $lines = array_filter(explode("\n", trim($content)));

        self::assertCount(3, $lines);

        $record1 = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
        $record2 = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);
        $record3 = json_decode($lines[2], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('chain_start', $record1['event']);
        self::assertSame('step_start', $record2['event']);
        self::assertSame('step_result', $record3['event']);
    }

    #[Test]
    public function createsDirectoryIfNotExists(): void
    {
        $deepDir = $this->logDir . '/nested/deep';
        $deepFile = $deepDir . '/audit.jsonl';
        $logger = new JsonlAuditLogger($deepFile);

        $logger->logChainStart('test', 'Task');

        self::assertFileExists($deepFile);

        // Cleanup
        unlink($deepFile);
        @rmdir($deepDir);
        @rmdir($this->logDir . '/nested');
        @rmdir($this->logDir);
    }

    #[Test]
    public function appendToExistingFile(): void
    {
        // Write first record
        $this->logger->logChainStart('chain1', 'Task 1');

        // Create new logger instance for same file
        $logger2 = new JsonlAuditLogger($this->logFile);
        $logger2->logChainStart('chain2', 'Task 2');

        $content = file_get_contents($this->logFile);
        $lines = array_filter(explode("\n", trim($content)));

        self::assertCount(2, $lines);
    }

    #[Test]
    public function timestampIsUtcIso8601(): void
    {
        $this->logger->logChainStart('test', 'Task');

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $record['ts']);
    }

    #[Test]
    public function throwsRuntimeExceptionWhenDirectoryNotWritable(): void
    {
        // Используем readonly-путь в /proc, который гарантированно не существует как директория
        // и не может быть создан
        $logger = new JsonlAuditLogger('/proc/impossible/path/audit.jsonl');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create audit log directory');

        $logger->logChainStart('test', 'Task');
    }
}
