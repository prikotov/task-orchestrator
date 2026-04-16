<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Tests\Unit\Infrastructure\Service\Chain;

use TasK\Orchestrator\Infrastructure\Service\Chain\JsonlAuditLogger;
use TasK\Orchestrator\Infrastructure\Service\Chain\JsonlAuditLoggerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const JSON_THROW_ON_ERROR;

#[CoversClass(JsonlAuditLoggerFactory::class)]
final class JsonlAuditLoggerFactoryTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/task_audit_factory_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        $logFile = $this->logDir . '/audit.jsonl';
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        if (is_dir($this->logDir)) {
            @rmdir($this->logDir);
        }
    }

    #[Test]
    public function createReturnsJsonlAuditLogger(): void
    {
        $factory = new JsonlAuditLoggerFactory();
        $logger = $factory->create($this->logDir . '/audit.jsonl');

        self::assertInstanceOf(JsonlAuditLogger::class, $logger);

        // Verify the created logger works
        $logger->logChainStart('test', 'Task');
        $content = file_get_contents($this->logDir . '/audit.jsonl');
        self::assertNotFalse($content);

        $record = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('chain_start', $record['event']);
    }
}
