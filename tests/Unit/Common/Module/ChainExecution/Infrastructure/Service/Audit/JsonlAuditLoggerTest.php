<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Infrastructure\Service\Audit;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Audit\ChainResultAuditDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Audit\StepAuditStatusDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\Audit\JsonlAuditLoggerService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const JSON_THROW_ON_ERROR;

#[CoversClass(JsonlAuditLoggerService::class)]
final class JsonlAuditLoggerTest extends TestCase
{
    private string $logFile;
    private string $logDir;
    private JsonlAuditLoggerService $logger;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/task_audit_chain_exec_' . uniqid();
        $this->logFile = $this->logDir . '/audit.jsonl';
        $this->logger = new JsonlAuditLoggerService($this->logFile);
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
    public function logChainStartWritesCorrectRecord(): void
    {
        $this->logger->logChainStart('implement', 'Build feature X');

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('chain_start', $record['event']);
        self::assertSame('implement', $record['chain']);
        self::assertSame('Build feature X', $record['task']);
    }

    #[Test]
    public function logStepStartWritesCorrectRecord(): void
    {
        $this->logger->logStepStart('implement', 1, 'analyst', 'pi');

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('step_start', $record['event']);
        self::assertSame(1, $record['step']);
        self::assertSame('analyst', $record['role']);
    }

    #[Test]
    public function logStepResultWritesSuccessRecord(): void
    {
        $result = ChainRunResultVo::createSuccess(
            outputText: 'Done',
            inputTokens: 1500,
            outputTokens: 800,
            cost: 0.023,
        );

        $this->logger->logStepResult('implement', 1, 'analyst', 'pi', $result, 5432.0);

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('step_result', $record['event']);
        self::assertSame(1500, $record['input_tokens']);
        self::assertSame(800, $record['output_tokens']);
        self::assertSame(0.023, $record['cost']);
        self::assertSame('success', $record['status']);
        self::assertArrayNotHasKey('error_message', $record);
    }

    #[Test]
    public function logStepResultWritesErrorRecord(): void
    {
        $result = ChainRunResultVo::createError(
            errorMessage: 'Timeout exceeded',
            exitCode: 124,
        );

        $this->logger->logStepResult('implement', 2, 'developer', 'pi', $result, 300000.0);

        $content = file_get_contents($this->logFile);
        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);

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
        self::assertSame('success', $record['status']);
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
}
