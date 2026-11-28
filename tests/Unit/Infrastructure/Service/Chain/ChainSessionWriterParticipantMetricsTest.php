<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\ChainSessionFileStorage;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\ChainSessionLogger;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\ChainSessionWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Roundtrip-тест модельных метрик шага в session.json: запись → чтение (resume).
 *
 * Новые сессии сохраняют model/provider/thinking и кэш-токены в rounds[];
 * старые session.json без этих полей читаются без ошибок — поля «н/д».
 */
#[CoversClass(ChainSessionLogger::class)]
#[CoversClass(ChainSessionWriter::class)]
#[CoversClass(ChainSessionFileStorage::class)]
final class ChainSessionWriterParticipantMetricsTest extends TestCase
{
    private string $tmpDir;

    private ChainSessionLogger $logger;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/chain_session_metrics_test_' . uniqid();
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
    public function logRoundPersistsModelAndCacheTokensIntoSessionJson(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Metrics topic',
            facilitator: 'team_lead_alex',
            participants: ['backend_developer_levsha'],
            maxRounds: 5,
            complexity: 3,
        );

        $this->logger->logRound(
            step: 1,
            round: 1,
            role: 'team_lead_alex',
            isFacilitator: true,
            systemPrompt: 'System',
            userPrompt: 'User',
            response: 'Response',
            duration: 8.0,
            inputTokens: 1000,
            outputTokens: 500,
            cost: 0.02,
            model: 'glm-5.3-flash',
            provider: 'zai',
            thinking: 'high',
            cacheReadTokens: 8000,
            cacheWriteTokens: 500,
        );
        $this->logger->completeSession('Done', 8.0, 1000, 500, 0.02, 1);

        $data = $this->decodeSessionJson($sessionDir);

        self::assertCount(1, $data['rounds']);
        self::assertSame('glm-5.3-flash', $data['rounds'][0]['model']);
        self::assertSame('zai', $data['rounds'][0]['provider']);
        self::assertSame('high', $data['rounds'][0]['thinking']);
        self::assertSame(8000, $data['rounds'][0]['cache_read_tokens']);
        self::assertSame(500, $data['rounds'][0]['cache_write_tokens']);
    }

    #[Test]
    public function logRoundWithoutModelDataPersistsNulls(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Metrics topic',
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
        $this->logger->completeSession('Done', 2.0, 100, 50, 0.01, 1);

        $data = $this->decodeSessionJson($sessionDir);

        self::assertNull($data['rounds'][0]['model']);
        self::assertNull($data['rounds'][0]['provider']);
        self::assertNull($data['rounds'][0]['thinking']);
        self::assertSame(0, $data['rounds'][0]['cache_read_tokens']);
        self::assertSame(0, $data['rounds'][0]['cache_write_tokens']);
    }

    #[Test]
    public function resumeParsesModelFieldsFromSessionJson(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Metrics topic',
            facilitator: 'team_lead_alex',
            participants: ['backend_developer_levsha'],
            maxRounds: 5,
            complexity: 3,
        );
        $this->logger->logRound(
            step: 1,
            round: 1,
            role: 'team_lead_alex',
            isFacilitator: true,
            systemPrompt: 'System',
            userPrompt: 'User',
            response: 'Response',
            duration: 8.0,
            inputTokens: 1000,
            outputTokens: 500,
            cost: 0.02,
            model: 'glm-5.3-flash',
            provider: 'zai',
            thinking: 'high',
            cacheReadTokens: 8000,
            cacheWriteTokens: 500,
        );
        $this->logger->updateSessionState(1);

        $resumeLogger = new ChainSessionLogger(
            $this->tmpDir . '/var/agent/chains',
            $this->tmpDir,
        );
        $resumeLogger->resumeSession($sessionDir);

        $roundFiles = $resumeLogger->getRoundFiles();
        self::assertCount(1, $roundFiles);
        self::assertTrue(array_is_list($roundFiles));
        $round = $roundFiles[0] ?? null;
        self::assertNotNull($round);
        self::assertSame('glm-5.3-flash', $round['model']);
        self::assertSame('zai', $round['provider']);
        self::assertSame('high', $round['thinking']);
        self::assertSame(8000, $round['cache_read_tokens']);
        self::assertSame(500, $round['cache_write_tokens']);
    }

    #[Test]
    public function resumeOldSessionJsonWithoutModelFieldsDoesNotFail(): void
    {
        // Старая сессия (до появления модельных полей): rounds[] без model/provider/thinking/cache_*.
        $sessionDir = $this->tmpDir . '/var/agent/chains/brainstorm/2026-11-20_15-11-26';
        mkdir($sessionDir, 0777, true);
        file_put_contents($sessionDir . '/topic.md', 'Old topic');
        file_put_contents($sessionDir . '/discussion_history.md', '');
        file_put_contents($sessionDir . '/facilitator_journal.md', '');
        file_put_contents(
            $sessionDir . '/session.json',
            (string) json_encode([
                'chain' => 'brainstorm',
                'topic_file' => 'topic.md',
                'facilitator' => 'team_lead_alex',
                'participants' => ['backend_developer_levsha'],
                'max_rounds' => 5,
                'complexity' => 3,
                'completed_steps' => 2,
                'completed_rounds' => 1,
                'discussion_history_file' => 'discussion_history.md',
                'facilitator_journal_file' => 'facilitator_journal.md',
                'rounds' => [
                    [
                        'step' => 1,
                        'round' => 1,
                        'role' => 'team_lead_alex',
                        'is_facilitator' => true,
                        'system_prompt_file' => 'step_001_round_001_team_lead_alex_1_system.md',
                        'user_prompt_file' => 'step_001_round_001_team_lead_alex_3_user.md',
                        'duration' => 8.0,
                        'input_tokens' => 1000,
                        'output_tokens' => 500,
                        'cost' => 0.02,
                        'response_file' => 'step_001_round_001_team_lead_alex_4_response.md',
                    ],
                ],
                'invocation' => [],
                'budget' => null,
                'status' => 'interrupted',
                'completion_reason' => 'timeout',
                'started_at' => '2026-11-20T15:11:26+03:00',
                'updated_at' => '2026-11-20T15:20:00+03:00',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $resumeLogger = new ChainSessionLogger(
            $this->tmpDir . '/var/agent/chains',
            $this->tmpDir,
        );
        $resumeLogger->resumeSession($sessionDir);

        $roundFiles = $resumeLogger->getRoundFiles();
        self::assertTrue(array_is_list($roundFiles));
        $round = $roundFiles[0] ?? null;
        self::assertNotNull($round);
        self::assertNull($round['model']);
        self::assertNull($round['provider']);
        self::assertNull($round['thinking']);
        self::assertSame(0, $round['cache_read_tokens']);
        self::assertSame(0, $round['cache_write_tokens']);

        $state = $resumeLogger->getResumedState();
        self::assertNotNull($state);
        self::assertSame(1, $state->getCompletedRounds());
    }

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
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->rmdirRecursive($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
