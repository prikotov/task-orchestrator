<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\ChainSessionFileStorage;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\ChainSessionLogger;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\ChainSessionReader;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\ChainSessionWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainSessionLogger::class)]
#[CoversClass(ChainSessionReader::class)]
#[CoversClass(ChainSessionWriter::class)]
#[CoversClass(ChainSessionFileStorage::class)]
final class ChainSessionLoggerResponsePathsTest extends TestCase
{
    private string $tmpDir;

    private ChainSessionLogger $logger;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/chain_session_paths_test_' . uniqid();
        $this->logger = new ChainSessionLogger(
            $this->tmpDir . '/var/agent/chains',
            $this->tmpDir,
        );
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tmpDir);
    }

    #[Test]
    public function getResponseFilePathsReturnsRoleAndPath(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Test topic',
            facilitator: 'team_lead_alex',
            participants: ['system_architect_loki', 'backend_developer_levsha'],
            maxRounds: 10,
        );

        // Шаг 1 — facilitator (не participant, не попадает в список)
        $this->logger->logRound(
            step: 1,
            round: 1,
            role: 'team_lead_alex',
            isFacilitator: true,
            systemPrompt: 'System',
            userPrompt: 'User',
            response: 'Response',
            duration: 1.0,
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.01,
        );

        // Шаг 2 — participant (попадает в список)
        $this->logger->logRound(
            step: 2,
            round: 1,
            role: 'system_architect_loki',
            isFacilitator: false,
            systemPrompt: 'System 2',
            userPrompt: 'User 2',
            response: 'Response 2',
            duration: 2.0,
            inputTokens: 200,
            outputTokens: 100,
            cost: 0.02,
        );

        // Шаг 3 — ещё participant (попадает в список)
        $this->logger->logRound(
            step: 3,
            round: 1,
            role: 'backend_developer_levsha',
            isFacilitator: false,
            systemPrompt: 'System 3',
            userPrompt: 'User 3',
            response: 'Response 3',
            duration: 1.5,
            inputTokens: 150,
            outputTokens: 75,
            cost: 0.015,
        );

        $paths = $this->logger->getResponseFilePaths(3);

        self::assertCount(2, $paths);

        // Проверяем структуру первого participant
        self::assertSame('system_architect_loki', $paths[0]['role']);
        self::assertStringContainsString('step_002_round_001_system_architect_loki_4_response.md', $paths[0]['path']);

        // Проверяем структуру второго participant
        self::assertSame('backend_developer_levsha', $paths[1]['role']);
        self::assertStringContainsString('step_003_round_001_backend_developer_levsha_4_response.md', $paths[1]['path']);
    }

    #[Test]
    public function getResponseFilePathsExcludesFacilitatorRounds(): void
    {
        $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Test topic',
            facilitator: 'team_lead_alex',
            participants: ['system_architect_loki'],
            maxRounds: 10,
        );

        // Только facilitator — не должен попасть в результат
        $this->logger->logRound(
            step: 1,
            round: 1,
            role: 'team_lead_alex',
            isFacilitator: true,
            systemPrompt: 'System',
            userPrompt: 'User',
            response: 'Response',
            duration: 1.0,
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.01,
        );

        $paths = $this->logger->getResponseFilePaths(1);

        self::assertCount(0, $paths);
    }

    #[Test]
    public function getResponseFilePathsFiltersByStep(): void
    {
        $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Test topic',
            facilitator: 'team_lead_alex',
            participants: ['system_architect_loki', 'backend_developer_levsha'],
            maxRounds: 10,
        );

        $this->logger->logRound(
            step: 1,
            round: 1,
            role: 'system_architect_loki',
            isFacilitator: false,
            systemPrompt: 'System 1',
            userPrompt: 'User 1',
            response: 'Response 1',
            duration: 1.0,
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.01,
        );

        $this->logger->logRound(
            step: 2,
            round: 1,
            role: 'backend_developer_levsha',
            isFacilitator: false,
            systemPrompt: 'System 2',
            userPrompt: 'User 2',
            response: 'Response 2',
            duration: 2.0,
            inputTokens: 200,
            outputTokens: 100,
            cost: 0.02,
        );

        // upToStep=1 — должен вернуть только шаг 1
        $paths = $this->logger->getResponseFilePaths(1);
        self::assertCount(1, $paths);
        self::assertSame('system_architect_loki', $paths[0]['role']);

        // upToStep=2 — должен вернуть оба шага
        $allPaths = $this->logger->getResponseFilePaths(2);
        self::assertCount(2, $allPaths);
    }

    #[Test]
    public function getResponseFilePathsReturnsEmptyWhenNoSession(): void
    {
        $logger = new ChainSessionLogger(
            sys_get_temp_dir() . '/nonexistent',
            sys_get_temp_dir(),
        );

        $paths = $logger->getResponseFilePaths(10);

        self::assertSame([], $paths);
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
