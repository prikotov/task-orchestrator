<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\ChainSessionLogger;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\ChainSessionWriter;

/**
 * Чистый старт сессии и нормализация пустого error_message при resume.
 *
 * Закрывает находки ревью PR #527:
 * - OBS-6 (Пуаро): инвариант «startSession() начинает с чистого состояния» —
 *   переиспользование экземпляра Writer (resume → startSession) не наследует
 *   накопленное состояние прежней сессии: rounds, invocation, budget,
 *   resumedState. Мутационная проверка: удаление любого сброса в
 *   startSession() роняет тест startSessionAfterResumeStartsWithCleanState().
 * - QA-3 (Хаус): пустая строка error_message в rounds[i] при resume
 *   эквивалентна отсутствию ключа ('' ≡ «нет сообщения»); перезапись
 *   session.json ключ не возвращает — поведение задокументировано док-гардом
 *   в resumeSession() и закреплено тестом emptyErrorMessageOnResumeIsEquivalentToMissingKey().
 */
#[CoversClass(ChainSessionWriter::class)]
#[CoversClass(ChainSessionLogger::class)]
final class ChainSessionWriterStateResetTest extends TestCase
{
    private string $tmpDir;

    private ChainSessionLogger $logger;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/chain_session_state_reset_test_' . uniqid();
        $this->logger = new ChainSessionLogger($this->tmpDir . '/var/agent/chains', $this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tmpDir);
    }

    #[Test]
    public function startSessionAfterResumeStartsWithCleanState(): void
    {
        // Arrange: первая сессия с раундом, верхнеуровневым invocation и бюджетом.
        $firstDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'First session',
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
            systemPrompt: 'System prompt',
            userPrompt: 'User prompt',
            response: 'Response body',
            duration: 1.0,
            inputTokens: 10,
            outputTokens: 5,
            cost: 0.001,
            invocation: 'agent:codex run 1',
        );
        $this->logger->logInvocation(['agent' => 'codex', 'run' => 1]);
        $this->logger->setBudget(new DynamicLoopBudgetVo(maxCostTotal: 10.0, maxCostPerStep: 2.0));
        $this->logger->updateSessionState(1);

        // Act: resume первой сессии тем же экземпляром, затем новая сессия.
        $this->logger->resumeSession($firstDir);
        self::assertNotNull($this->logger->getResumedState());
        self::assertCount(1, $this->logger->getRoundFiles());

        // Предусловие: resume восстановил и budget/invocation. Перезапись
        // session.json первой сессии идёт из in-memory состояния
        // ($this->invocation/$this->budget), поэтому непустой invocation
        // и восстановленный budget в файле доказывают именно восстановление
        // состояния экземпляра — каждый из четырёх сбросов получает явное
        // предусловие, симметричное проверкам resumedState/roundFiles выше.
        $this->logger->updateSessionState(1);
        $firstData = $this->decodeSessionJson($firstDir);
        self::assertSame(['agent' => 'codex', 'run' => 1], $firstData['invocation']);
        self::assertNotNull($firstData['budget']);
        self::assertEquals(10.0, $firstData['budget']['max_cost_total']);
        self::assertEquals(2.0, $firstData['budget']['max_cost_per_step']);

        $secondDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Second session',
            facilitator: 'team_lead_alex',
            participants: ['backend_developer_levsha'],
            maxRounds: 5,
            complexity: 2,
        );

        // Assert: новая сессия не наследует ни одного поля прежней сессии.
        // (Пути каталогов не сравниваем: имя каталога форматируется с точностью
        // до секунды, поэтому два запуска в одну секунду дают одну строку пути —
        // инвариант касается состояния экземпляра, а не путей.)
        self::assertSame([], $this->logger->getRoundFiles());
        self::assertNull($this->logger->getResumedState());

        $data = $this->decodeSessionJson($secondDir);
        self::assertSame([], $data['rounds']);
        self::assertSame([], $data['invocation']);
        self::assertNull($data['budget']);
    }

    #[Test]
    public function emptyErrorMessageOnResumeIsEquivalentToMissingKey(): void
    {
        // Arrange: error-раунд с пустым error_message (пишется в session.json как есть).
        $sessionDir = $this->logger->startSession(
            chainName: 'brainstorm',
            topic: 'Empty error message',
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
            systemPrompt: 'System prompt',
            userPrompt: 'User prompt',
            response: 'not used for error rounds',
            duration: 1.0,
            inputTokens: 10,
            outputTokens: 5,
            cost: 0.001,
            isError: true,
            errorMessage: '',
        );
        $this->logger->updateSessionState(1);

        $written = $this->decodeSessionJson($sessionDir);
        self::assertArrayHasKey('error_message', $written['rounds'][0]);
        self::assertSame('', $written['rounds'][0]['error_message']);

        // Act/Assert: resume нормализует '' к отсутствию ключа;
        // артефакт ошибки при этом сохраняется.
        $this->logger->resumeSession($sessionDir);
        $roundFiles = $this->logger->getRoundFiles();
        self::assertCount(1, $roundFiles);
        self::assertArrayHasKey('error', $roundFiles[0]);
        self::assertArrayNotHasKey('error_message', $roundFiles[0]);

        // Идемпотентность: перезапись session.json ключ не возвращает.
        $this->logger->updateSessionState(1);
        $rewritten = $this->decodeSessionJson($sessionDir);
        self::assertArrayHasKey('error_file', $rewritten['rounds'][0]);
        self::assertArrayNotHasKey('error_message', $rewritten['rounds'][0]);
    }

    // --- Helpers ---

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
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
