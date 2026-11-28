<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\ChainSessionFileStorage;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\ChainSessionLogger;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\ChainSessionReader;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\ChainSessionWriter;

/**
 * Контракт Domain-границы getRoundFiles(): публичный результат — упорядоченный
 * list значений с ключами 0..n-1.
 *
 * Step-ключи остаются внутренней деталью хранения: переиндексация допустима
 * только на границе чтения (ChainSessionReader::getRoundFiles()), внутреннее
 * хранение Writer, поле step в session.json и фильтрация getResponseFilePaths()
 * по step не меняются.
 */
#[CoversClass(ChainSessionLogger::class)]
#[CoversClass(ChainSessionReader::class)]
#[CoversClass(ChainSessionWriter::class)]
#[CoversClass(ChainSessionFileStorage::class)]
final class ChainSessionReaderRoundFilesContractTest extends TestCase
{
    private string $tmpDir;

    private ChainSessionLogger $logger;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/chain_session_roundfiles_test_' . uniqid();
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
    public function emptySessionReturnsEmptyList(): void
    {
        $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Round files contract',
            facilitator: 'team_lead_alex',
            participants: ['backend_developer_levsha'],
            maxRounds: 5,
            complexity: 3,
        );

        $roundFiles = $this->logger->getRoundFiles();

        self::assertSame([], $roundFiles);
        self::assertTrue(array_is_list($roundFiles));
    }

    #[Test]
    public function singleRoundIsListWithSequentialKeysAndPreservedValues(): void
    {
        $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Round files contract',
            facilitator: 'team_lead_alex',
            participants: ['backend_developer_levsha'],
            maxRounds: 5,
            complexity: 3,
        );
        $this->logger->logRound(
            step: 1,
            round: 1,
            role: 'backend_developer_levsha',
            isFacilitator: false,
            systemPrompt: 'System',
            userPrompt: 'User',
            response: 'Response',
            duration: 2.0,
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.01,
        );

        $roundFiles = $this->logger->getRoundFiles();

        self::assertCount(1, $roundFiles);
        self::assertTrue(array_is_list($roundFiles));
        self::assertSame([0], array_keys($roundFiles));
        $round = $roundFiles[0];
        self::assertSame('backend_developer_levsha', $round['role']);
        self::assertFalse($round['is_facilitator']);
        self::assertSame(1, $round['round']);
        self::assertSame(2.0, $round['duration']);
        self::assertSame(100, $round['input_tokens']);
        self::assertSame(50, $round['output_tokens']);
        self::assertSame(0.01, $round['cost']);
    }

    #[Test]
    public function resumeWithNonSequentialStepsReturnsListAndKeepsInternalSteps(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Round files contract',
            facilitator: 'team_lead_alex',
            participants: ['backend_developer_levsha'],
            maxRounds: 5,
            complexity: 3,
        );
        $this->logRoundStepsOneThreeAndFive($this->logger);
        $this->logger->interruptSession();

        $resumeLogger = new ChainSessionLogger(
            $this->tmpDir . '/var/agent/chains',
            $this->tmpDir,
        );
        $resumeLogger->resumeSession($sessionDir);

        // Публичный контракт: list 0..n-1 с сохранением порядка и значений.
        $roundFiles = $resumeLogger->getRoundFiles();
        self::assertCount(3, $roundFiles);
        self::assertTrue(array_is_list($roundFiles));
        self::assertSame([0, 1, 2], array_keys($roundFiles));
        self::assertSame(
            [
                ['role' => 'team_lead_alex', 'round' => 1, 'input_tokens' => 10],
                ['role' => 'backend_developer_levsha', 'round' => 2, 'input_tokens' => 20],
                ['role' => 'backend_developer_levsha', 'round' => 3, 'input_tokens' => 30],
            ],
            array_map(
                static fn (array $round): array => [
                    'role' => $round['role'],
                    'round' => $round['round'],
                    'input_tokens' => $round['input_tokens'],
                ],
                $roundFiles,
            ),
        );

        // Внутреннее хранение: step-ключи в session.json не переиндексированы.
        $data = $this->decodeSessionJson($sessionDir);
        self::assertSame([1, 3, 5], array_column($data['rounds'], 'step'));

        // Фильтрация response-путей продолжит сопоставлять по внутренним step.
        $relativeDir = substr($sessionDir, strlen($this->tmpDir) + 1);
        self::assertSame([], $resumeLogger->getResponseFilePaths(2));
        self::assertSame(
            [[
                'role' => 'backend_developer_levsha',
                'path' => $relativeDir . '/step_003_round_002_backend_developer_levsha_4_response.md',
            ]],
            $resumeLogger->getResponseFilePaths(3),
        );
        self::assertSame(
            [
                'backend_developer_levsha',
                'backend_developer_levsha',
            ],
            array_column($resumeLogger->getResponseFilePaths(5), 'role'),
        );
    }

    private function logRoundStepsOneThreeAndFive(ChainSessionLogger $logger): void
    {
        $logger->logRound(
            step: 1,
            round: 1,
            role: 'team_lead_alex',
            isFacilitator: true,
            systemPrompt: 'System 1',
            userPrompt: 'User 1',
            response: 'Facilitator response 1',
            duration: 1.0,
            inputTokens: 10,
            outputTokens: 5,
            cost: 0.001,
        );
        $logger->logRound(
            step: 3,
            round: 2,
            role: 'backend_developer_levsha',
            isFacilitator: false,
            systemPrompt: 'System 3',
            userPrompt: 'User 3',
            response: 'Participant response 3',
            duration: 2.0,
            inputTokens: 20,
            outputTokens: 10,
            cost: 0.002,
        );
        $logger->logRound(
            step: 5,
            round: 3,
            role: 'backend_developer_levsha',
            isFacilitator: false,
            systemPrompt: 'System 5',
            userPrompt: 'User 5',
            response: 'Participant response 5',
            duration: 3.0,
            inputTokens: 30,
            outputTokens: 15,
            cost: 0.003,
        );
    }

    /** @return array<string, mixed> */
    private function decodeSessionJson(string $sessionDir): array
    {
        $raw = (string) file_get_contents($sessionDir . '/session.json');
        $decoded = json_decode($raw, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
