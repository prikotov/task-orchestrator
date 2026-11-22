<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\JsonlAuditLogger;
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
    public function multipleCallsAppendLines(): void
    {
        $this->logger->logChainStart('implement', 'Task A');
        $this->logger->logStepStart('implement', 1, 'analyst', 'pi');

        $content = file_get_contents($this->logFile);
        $lines = array_filter(explode("\n", trim($content)));

        self::assertCount(2, $lines);

        $record1 = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
        $record2 = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('chain_start', $record1['event']);
        self::assertSame('step_start', $record2['event']);
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
    public function logDynamicStepResultWritesModelProviderThinkingAndCacheTokens(): void
    {
        $result = new DynamicRoundResultVo(
            round: 2,
            role: 'backend_developer',
            isFacilitator: false,
            outputText: 'Done',
            inputTokens: 1000,
            outputTokens: 500,
            cost: 0.02,
            duration: 12.0,
            model: 'mimo-v2.6-pro',
            provider: 'xiaomi-token-plan-sgp',
            thinking: 'high',
            cacheReadTokens: 4000,
            cacheWriteTokens: 500,
        );

        $this->logger->logDynamicStepResult('brainstorm', 5, 'backend_developer', 'pi', $result, 1234.56);

        $content = file_get_contents($this->logFile);
        self::assertNotFalse($content);

        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        // Новые ключи участников: модель, провайдер, thinking, кэш-токены.
        self::assertSame('mimo-v2.6-pro', $record['model']);
        self::assertSame('xiaomi-token-plan-sgp', $record['provider']);
        self::assertSame('high', $record['thinking']);
        self::assertSame(4000, $record['cache_read_tokens']);
        self::assertSame(500, $record['cache_write_tokens']);

        // Базовые ключи step_result.
        self::assertSame('step_result', $record['event']);
        self::assertSame('brainstorm', $record['chain']);
        self::assertSame(5, $record['step']);
        self::assertSame('backend_developer', $record['role']);
        self::assertSame('pi', $record['runner']);
        self::assertSame(1000, $record['input_tokens']);
        self::assertSame(500, $record['output_tokens']);
        self::assertSame(0.02, $record['cost']);
        self::assertSame(1234.6, $record['duration_ms']);
        self::assertSame('success', $record['status']);
        self::assertArrayNotHasKey('error_message', $record);
    }

    #[Test]
    public function logDynamicStepResultWithoutMetricsWritesNullModelAndZeroCache(): void
    {
        $result = new DynamicRoundResultVo(
            round: 1,
            role: 'system_analyst',
            isFacilitator: false,
            outputText: 'Analysis',
            inputTokens: 800,
            outputTokens: 400,
            cost: 0.01,
            duration: 5.0,
        );

        $this->logger->logDynamicStepResult('brainstorm', 3, 'system_analyst', 'pi', $result, 5000.0);

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        // Раннер не сообщил модельные поля и кэш: ключи присутствуют, значения null/0.
        self::assertNull($record['model']);
        self::assertNull($record['provider']);
        self::assertNull($record['thinking']);
        self::assertSame(0, $record['cache_read_tokens']);
        self::assertSame(0, $record['cache_write_tokens']);
        self::assertSame('success', $record['status']);
    }

    #[Test]
    public function logDynamicStepResultWritesErrorStatusAndMessage(): void
    {
        $result = new DynamicRoundResultVo(
            round: 1,
            role: 'team_lead',
            isFacilitator: true,
            outputText: '',
            inputTokens: 0,
            outputTokens: 0,
            cost: 0.0,
            duration: 1.0,
            isError: true,
            errorMessage: 'Agent crashed',
        );

        $this->logger->logDynamicStepResult('brainstorm', 1, 'team_lead', 'pi', $result, 1000.0);

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('error', $record['status']);
        self::assertSame('Agent crashed', $record['error_message']);
    }

    #[Test]
    public function throwsRuntimeExceptionWhenDirectoryNotWritable(): void
    {
        $logger = new JsonlAuditLogger('/proc/impossible/path/audit.jsonl');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create audit log directory');

        // Without the @ operator (forbidden by PHPMD), mkdir() emits a benign
        // E_WARNING on the unwritable path before the fail-fast RuntimeException
        // is thrown; suppress it for the duration of this assertion.
        set_error_handler(static fn (): bool => true);

        try {
            $logger->logChainStart('test', 'Task');
        } finally {
            restore_error_handler();
        }
    }
}
