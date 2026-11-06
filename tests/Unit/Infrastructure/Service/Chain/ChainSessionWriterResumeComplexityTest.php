<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use RuntimeException;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\ChainSessionLogger;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\ChainSessionWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Resume сессии без поля complexity в session.json обязан падать с ошибкой:
 * fallback на C3 по умолчанию запрещён.
 */
#[CoversClass(ChainSessionWriter::class)]
#[CoversClass(ChainSessionLogger::class)]
final class ChainSessionWriterResumeComplexityTest extends TestCase
{
    private string $tmpDir;
    private ChainSessionLogger $logger;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/chain_session_resume_complexity_test_' . uniqid();
        $this->logger = new ChainSessionLogger($this->tmpDir . '/var/agent/chains', $this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tmpDir);
    }

    #[Test]
    public function resumeSessionWithoutComplexityFieldThrows(): void
    {
        // Arrange: создаём сессию и убираем поле complexity из session.json
        $sessionDir = $this->logger->startSession(
            chainName: 'test_chain',
            topic: 'Test topic',
            facilitator: 'analyst',
            participants: ['dev'],
            maxRounds: 10,
            complexity: 3,
        );
        $jsonPath = $sessionDir . '/session.json';
        $content = file_get_contents($jsonPath);
        self::assertNotFalse($content);
        $data = json_decode($content, true);
        self::assertIsArray($data);
        unset($data['complexity']);
        file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Assert: resume без complexity падает, C3 по умолчанию не подставляется
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid complexity value in session.json of ' . $sessionDir . ': expected integer 0-5 (C0-C5).');

        // Act
        $this->logger->resumeSession($sessionDir);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
