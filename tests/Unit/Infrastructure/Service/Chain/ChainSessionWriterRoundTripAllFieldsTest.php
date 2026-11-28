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
 * Теловой roundtrip полной (18-полевой) формы записи раунда: каждое поле,
 * записанное logRound(), проходит цикл session.json → parseRoundFiles() →
 * getRoundFiles() → повторная запись session.json без потери.
 *
 * Сторожит синхронность белых списков writeSessionJson()/parseRoundFiles() —
 * телевой уровень, который doc-форма (сторож ChainSessionRoundShapeGuardTest)
 * не закрывает: намеренный пропуск любого поля белого списка роняет тест.
 *
 * 18-полевая форма — объединение двух потоков записи Writer, поэтому тест
 * покрывает оба потока всеми полями: success-запись (response + invocation +
 * модельные поля + кэш-токены) и error-запись (error/error_message +
 * invocation) — закрывает QA-1/QA-2 QA-отчёта ветки task/writer-round-shape-sync.
 *
 * Дополнительно закрепляет решение по BZ-1: верхнеуровневое invocation
 * (поток logInvocation()) восстанавливается при resume и переживает перезапись
 * session.json до ближайшего logInvocation(); семантика latest-wins сохраняется.
 */
#[CoversClass(ChainSessionLogger::class)]
#[CoversClass(ChainSessionReader::class)]
#[CoversClass(ChainSessionWriter::class)]
#[CoversClass(ChainSessionFileStorage::class)]
final class ChainSessionWriterRoundTripAllFieldsTest extends TestCase
{
    private string $tmpDir;

    private ChainSessionLogger $logger;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/chain_session_roundtrip_test_' . uniqid();
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
    public function successRecordWithAllFieldsSurvivesResumeAndRewriteWithoutLoss(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Round trip all fields',
            facilitator: 'team_lead_alex',
            participants: ['backend_developer_levsha'],
            maxRounds: 5,
            complexity: 3,
        );
        $this->logger->logRound(
            step: 1,
            round: 2,
            role: 'backend_developer_levsha',
            isFacilitator: false,
            systemPrompt: 'System prompt',
            userPrompt: 'User prompt',
            response: 'Participant response body',
            duration: 12.3,
            inputTokens: 1234,
            outputTokens: 567,
            cost: 0.0123,
            invocation: 'agent:codex run 42',
            model: 'gpt-5.2',
            provider: 'openai',
            thinking: 'Step-by-step plan.',
            cacheReadTokens: 321,
            cacheWriteTokens: 654,
        );
        $this->logger->updateSessionState(1);

        // Публичная форма после resume: все поля байт-в-байт равны записанным.
        $resumed = $this->createResumedLogger($sessionDir);
        $roundFiles = $resumed->getRoundFiles();
        self::assertCount(1, $roundFiles);
        self::assertSame([
            'system' => 'step_001_round_002_backend_developer_levsha_1_system.md',
            'user' => 'step_001_round_002_backend_developer_levsha_3_user.md',
            'role' => 'backend_developer_levsha',
            'is_facilitator' => false,
            'round' => 2,
            'duration' => 12.3,
            'input_tokens' => 1234,
            'output_tokens' => 567,
            'cost' => 0.0123,
            'model' => 'gpt-5.2',
            'provider' => 'openai',
            'thinking' => 'Step-by-step plan.',
            'cache_read_tokens' => 321,
            'cache_write_tokens' => 654,
            'response' => 'step_001_round_002_backend_developer_levsha_4_response.md',
            'invocation' => 'agent:codex run 42',
        ], $roundFiles[0]);
        self::assertArrayNotHasKey('error', $roundFiles[0]);
        self::assertArrayNotHasKey('error_message', $roundFiles[0]);

        // Идемпотентность: перезапись session.json без новых раундов не теряет поля.
        $resumed->updateSessionState(1);
        self::assertSame([
            [
                'step' => 1,
                'round' => 2,
                'role' => 'backend_developer_levsha',
                'is_facilitator' => false,
                'system_prompt_file' => 'step_001_round_002_backend_developer_levsha_1_system.md',
                'user_prompt_file' => 'step_001_round_002_backend_developer_levsha_3_user.md',
                'duration' => 12.3,
                'input_tokens' => 1234,
                'output_tokens' => 567,
                'cost' => 0.0123,
                'model' => 'gpt-5.2',
                'provider' => 'openai',
                'thinking' => 'Step-by-step plan.',
                'cache_read_tokens' => 321,
                'cache_write_tokens' => 654,
                'response_file' => 'step_001_round_002_backend_developer_levsha_4_response.md',
                'invocation' => 'agent:codex run 42',
            ],
        ], $this->decodeSessionJson($sessionDir)['rounds']);
    }

    #[Test]
    public function errorRecordWithErrorFieldsAndInvocationSurvivesResumeAndRewrite(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Round trip error branch',
            facilitator: 'team_lead_alex',
            participants: ['backend_developer_levsha'],
            maxRounds: 5,
            complexity: 3,
        );
        $this->logger->logRound(
            step: 2,
            round: 1,
            role: 'backend_developer_levsha',
            isFacilitator: false,
            systemPrompt: 'System prompt',
            userPrompt: 'User prompt',
            response: 'not used for error rounds',
            duration: 1.5,
            inputTokens: 10,
            outputTokens: 5,
            cost: 0.001,
            invocation: 'agent:codex run 7',
            isError: true,
            errorMessage: 'Agent returned an error.',
        );
        $this->logger->updateSessionState(1);

        $resumed = $this->createResumedLogger($sessionDir);
        $roundFiles = $resumed->getRoundFiles();
        self::assertCount(1, $roundFiles);
        self::assertSame([
            'system' => 'step_002_round_001_backend_developer_levsha_1_system.md',
            'user' => 'step_002_round_001_backend_developer_levsha_3_user.md',
            'role' => 'backend_developer_levsha',
            'is_facilitator' => false,
            'round' => 1,
            'duration' => 1.5,
            'input_tokens' => 10,
            'output_tokens' => 5,
            'cost' => 0.001,
            'model' => null,
            'provider' => null,
            'thinking' => null,
            'cache_read_tokens' => 0,
            'cache_write_tokens' => 0,
            'error' => 'step_002_round_001_backend_developer_levsha_4_error.md',
            'error_message' => 'Agent returned an error.',
            'invocation' => 'agent:codex run 7',
        ], $roundFiles[0]);
        self::assertArrayNotHasKey('response', $roundFiles[0]);

        $resumed->updateSessionState(1);
        $rewrittenRounds = $this->decodeSessionJson($sessionDir)['rounds'];
        self::assertCount(1, $rewrittenRounds);
        self::assertSame(
            [
                'step' => 2,
                'round' => 1,
                'role' => 'backend_developer_levsha',
                'is_facilitator' => false,
                'system_prompt_file' => 'step_002_round_001_backend_developer_levsha_1_system.md',
                'user_prompt_file' => 'step_002_round_001_backend_developer_levsha_3_user.md',
                'duration' => 1.5,
                'input_tokens' => 10,
                'output_tokens' => 5,
                'cost' => 0.001,
                'model' => null,
                'provider' => null,
                'thinking' => null,
                'cache_read_tokens' => 0,
                'cache_write_tokens' => 0,
                'error_file' => 'step_002_round_001_backend_developer_levsha_4_error.md',
                'error_message' => 'Agent returned an error.',
                'invocation' => 'agent:codex run 7',
            ],
            $rewrittenRounds[0],
        );
    }

    #[Test]
    public function emptyStringInvocationSurvivesResumeAndRewrite(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Round trip empty invocation',
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
            systemPrompt: 'System 1',
            userPrompt: 'User 1',
            response: 'Response 1',
            duration: 1.0,
            inputTokens: 10,
            outputTokens: 5,
            cost: 0.001,
            invocation: 'agent:codex run 42',
        );
        $this->logger->logRound(
            step: 2,
            round: 2,
            role: 'backend_developer_levsha',
            isFacilitator: false,
            systemPrompt: 'System 2',
            userPrompt: 'User 2',
            response: 'Response 2',
            duration: 2.0,
            inputTokens: 20,
            outputTokens: 10,
            cost: 0.002,
            invocation: '',
        );
        $this->logger->updateSessionState(2);

        $resumed = $this->createResumedLogger($sessionDir);
        $roundFiles = $resumed->getRoundFiles();
        self::assertCount(2, $roundFiles);
        self::assertSame('agent:codex run 42', $roundFiles[0]['invocation']);
        self::assertSame('', $roundFiles[1]['invocation']);

        $resumed->updateSessionState(2);
        $rewrittenRounds = $this->decodeSessionJson($sessionDir)['rounds'];
        self::assertSame('agent:codex run 42', $rewrittenRounds[0]['invocation']);
        self::assertSame('', $rewrittenRounds[1]['invocation']);
    }

    #[Test]
    public function multiRoundWithInvocationOnNonSequentialStepsSurvivesResumeAndRewrite(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Round trip multi round',
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
            systemPrompt: 'System 1',
            userPrompt: 'User 1',
            response: 'Facilitator response 1',
            duration: 1.0,
            inputTokens: 10,
            outputTokens: 5,
            cost: 0.001,
            invocation: 'agent:codex run 1',
        );
        $this->logger->logRound(
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
            invocation: 'agent:codex run 3',
        );
        $this->logger->logRound(
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
            invocation: 'agent:codex run 5',
        );
        $this->logger->interruptSession();

        $resumed = $this->createResumedLogger($sessionDir);
        $roundFiles = $resumed->getRoundFiles();
        self::assertCount(3, $roundFiles);
        self::assertSame(
            ['agent:codex run 1', 'agent:codex run 3', 'agent:codex run 5'],
            array_column($roundFiles, 'invocation'),
        );

        // step-ключи внутренней записи при перезаписи не переиндексируются.
        $resumed->updateSessionState(3);
        $data = $this->decodeSessionJson($sessionDir);
        self::assertSame([1, 3, 5], array_column($data['rounds'], 'step'));
    }

    #[Test]
    public function resumeKeepsTopLevelInvocationUntilNextLogInvocation(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Top level invocation',
            facilitator: 'team_lead_alex',
            participants: ['backend_developer_levsha'],
            maxRounds: 5,
            complexity: 3,
        );
        $this->logger->logInvocation(['agent' => 'codex', 'timeout' => 300]);

        // Первая же запись после resume (ещё до logInvocation) не стирает
        // прежнее значение — закрывает окно потери при падении процесса.
        $resumed = $this->createResumedLogger($sessionDir);
        $resumed->updateSessionState(0);
        self::assertSame(
            ['agent' => 'codex', 'timeout' => 300],
            $this->decodeSessionJson($sessionDir)['invocation'],
        );

        // latest-wins: ближайший logInvocation() заменяет значение целиком.
        $resumed->logInvocation(['agent' => 'codex', 'resumed_from' => $sessionDir]);
        self::assertSame(
            ['agent' => 'codex', 'resumed_from' => $sessionDir],
            $this->decodeSessionJson($sessionDir)['invocation'],
        );
    }

    #[Test]
    public function sessionJsonWithoutInvocationFieldResumesAsEmptyList(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Old session without invocation',
            facilitator: 'team_lead_alex',
            participants: ['backend_developer_levsha'],
            maxRounds: 5,
            complexity: 3,
        );
        $this->rewriteSessionJson($sessionDir, static function (array $data): array {
            unset($data['invocation']);

            return $data;
        });

        $resumed = $this->createResumedLogger($sessionDir);
        $resumed->updateSessionState(0);
        self::assertSame([], $this->decodeSessionJson($sessionDir)['invocation']);
    }

    #[Test]
    public function nonArrayInvocationInSessionJsonIsNormalizedToEmptyList(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Corrupted invocation',
            facilitator: 'team_lead_alex',
            participants: ['backend_developer_levsha'],
            maxRounds: 5,
            complexity: 3,
        );
        $this->rewriteSessionJson(
            $sessionDir,
            static fn (array $data): array => [...$data, 'invocation' => 'corrupted'],
        );

        $resumed = $this->createResumedLogger($sessionDir);
        $resumed->updateSessionState(0);
        self::assertSame([], $this->decodeSessionJson($sessionDir)['invocation']);
    }

    // --- Helpers ---

    private function createResumedLogger(string $sessionDir): ChainSessionLogger
    {
        $resumed = new ChainSessionLogger(
            $this->tmpDir . '/var/agent/chains',
            $this->tmpDir,
        );
        $resumed->resumeSession($sessionDir);

        return $resumed;
    }

    /**
     * Перезаписывает session.json модификатором (имитация старой/повреждённой сессии).
     *
     * @param callable(array<string, mixed>): array<string, mixed> $mutate
     */
    private function rewriteSessionJson(string $sessionDir, callable $mutate): void
    {
        $raw = (string) file_get_contents($sessionDir . '/session.json');
        $decoded = json_decode($raw, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        $mutated = $mutate($decoded);
        file_put_contents(
            $sessionDir . '/session.json',
            (string) json_encode($mutated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
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
