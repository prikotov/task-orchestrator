<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Pi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\PiJsonlParser;

#[CoversClass(PiJsonlParser::class)]
final class PiJsonlParserTest extends TestCase
{
    private PiJsonlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PiJsonlParser();
    }

    #[Test]
    public function parseMessageEndWithContent(): void
    {
        $jsonl = implode("\n", [
            '{"type":"message_update","assistantMessageEvent":{"type":"text_delta","delta":"Hello "}}',
            '{"type":"message_update","assistantMessageEvent":{"type":"text_delta","delta":"World!"}}',
            '{"type":"message_end","message":{"role":"assistant","content":[{"type":"text","text":"Hello World!"}],"usage":{"input":100,"output":50,"turns":1,"cache":{"read":10,"write":5},"cost":{"total":0.01}},"model":"claude-3.5-sonnet"}}',
            '{"type":"agent_end","messages":[{"role":"user","content":[{"type":"text","text":"Hi"}]},{"role":"assistant","content":[{"type":"text","text":"Hello World!"}]}]}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Hello World!', $result['outputText']);
        self::assertSame(100, $result['inputTokens']);
        self::assertSame(50, $result['outputTokens']);
        self::assertSame(10, $result['cacheReadTokens']);
        self::assertSame(5, $result['cacheWriteTokens']);
        self::assertSame(0.01, $result['cost']);
        self::assertSame('claude-3.5-sonnet', $result['model']);
        self::assertSame(1, $result['turns']);
    }

    #[Test]
    public function parseEmptyInput(): void
    {
        $result = $this->parser->result();

        self::assertSame('', $result['outputText']);
        self::assertSame(0, $result['inputTokens']);
        self::assertEqualsWithDelta(0.0, $result['cost'], 0.0001);
    }

    #[Test]
    public function parseSkipsInvalidJson(): void
    {
        $jsonl = "not json\n{\"type\":\"message_end\",\"message\":{\"role\":\"assistant\",\"content\":[{\"type\":\"text\",\"text\":\"Result\"}],\"usage\":{\"input\":50,\"output\":25,\"turns\":1,\"cache\":{\"read\":0,\"write\":0},\"cost\":{\"total\":0.005}},\"model\":\"test\"}}\n{\"type\":\"agent_end\",\"messages\":[{\"role\":\"user\",\"content\":[{\"type\":\"text\",\"text\":\"Hi\"}]},{\"role\":\"assistant\",\"content\":[{\"type\":\"text\",\"text\":\"Result\"}]}]}";

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Result', $result['outputText']);
        self::assertSame(50, $result['inputTokens']);
    }

    #[Test]
    public function parseHandlesMissingFieldsGracefully(): void
    {
        $jsonl = '{"type":"message_end","message":{}}';

        $result = $this->feedJsonl($jsonl);

        self::assertSame('', $result['outputText']);
        self::assertSame(0, $result['inputTokens']);
        self::assertSame(0.0, $result['cost']);
        self::assertNull($result['model']);
    }

    #[Test]
    public function parseExtractsLastAssistantText(): void
    {
        // Multi-turn: assistant calls tool, then gives final answer
        $jsonl = implode("\n", [
            '{"type":"message_end","message":{"role":"assistant","content":[{"type":"text","text":"Let me check..."},{"type":"toolCall","toolCall":{"id":"1","name":"read"}}],"usage":{"input":100,"output":20,"turns":1,"cache":{"read":0,"write":0},"cost":{"total":0.005}},"model":"test"}}',
            '{"type":"message_end","message":{"role":"assistant","content":[{"type":"thinking","text":"..."},{"type":"text","text":"Final answer: 42"}],"usage":{"input":200,"output":30,"turns":2,"cache":{"read":0,"write":0},"cost":{"total":0.01}},"model":"test"}}',
            '{"type":"agent_end","messages":[{"role":"user","content":[{"type":"text","text":"question"}]},{"role":"assistant","content":[{"type":"text","text":"Let me check..."},{"type":"toolCall","toolCall":{"id":"1","name":"read"}}]},{"role":"user","content":[{"type":"toolResult","text":"file contents"}]},{"role":"assistant","content":[{"type":"thinking","text":"..."},{"type":"text","text":"Final answer: 42"}]}]}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Final answer: 42', $result['outputText']);
        self::assertSame(300, $result['inputTokens']); // 100 + 200
        self::assertSame(0.015, $result['cost']); // 0.005 + 0.01
    }

    #[Test]
    public function parseFallsBackToTextDeltasWithoutAgentEnd(): void
    {
        $jsonl = implode("\n", [
            '{"type":"message_update","assistantMessageEvent":{"type":"text_delta","delta":"Part1 "}}',
            '{"type":"message_update","assistantMessageEvent":{"type":"text_delta","delta":"Part2"}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Part1 Part2', $result['outputText']);
    }

    #[Test]
    public function feedHandlesCrlfLineEndings(): void
    {
        $this->parser->feed(
            '{"type":"message_end","message":{"usage":{"input":9,"output":4,"turns":1}}}' . "\r",
        );
        $this->parser->feed(
            '{"type":"agent_end","messages":[{"role":"assistant","content":[{"type":"text","text":"CRLF OK"}]}]}' . "\r",
        );

        $result = $this->parser->result();

        self::assertSame('CRLF OK', $result['outputText']);
        self::assertSame(9, $result['inputTokens']);
        self::assertSame(4, $result['outputTokens']);
    }

    #[Test]
    public function feedInterruptedBeforeAgentEndUsesTextDeltaFallback(): void
    {
        $this->parser->feed('{"type":"message_update","assistantMessageEvent":{"type":"text_delta","delta":"Partial "}}');
        $this->parser->feed('{"type":"message_update","assistantMessageEvent":{"type":"text_delta","delta":"answer"}}');
        $this->parser->feed('{"type":"message_end","message":{"usage":{"input":3,"output":2,"turns":1}}}');

        $result = $this->parser->result();

        self::assertSame('Partial answer', $result['outputText']);
        self::assertSame(3, $result['inputTokens']);
        self::assertSame(2, $result['outputTokens']);
    }

    #[Test]
    public function resetClearsPreviousState(): void
    {
        $this->parser->feed(
            '{"type":"agent_end","messages":[{"role":"assistant","content":[{"type":"text","text":"old"}]}]}',
        );
        $this->parser->reset();

        $result = $this->parser->result();

        self::assertSame('', $result['outputText']);
        self::assertSame(0, $result['inputTokens']);
    }

    #[Test]
    public function feedLargeTextDeltasFollowedByAgentEndKeepsPeakMemoryBoundedAndExtractsMetrics(): void
    {
        $deltaLine = sprintf(
            '{"type":"message_update","assistantMessageEvent":{"type":"text_delta","delta":"%s"}}',
            str_repeat('x', 160),
        );
        $iterations = intdiv(12 * 1024 * 1024, strlen($deltaLine)) + 1;

        memory_reset_peak_usage();
        $memoryBeforeParse = memory_get_usage(true);

        for ($i = 0; $i < $iterations; ++$i) {
            $this->parser->feed($deltaLine);
        }
        $this->parser->feed(
            '{"type":"message_end","message":{"usage":{"input":123,"output":45,"turns":2,'
            . '"cache":{"read":7,"write":3},"cost":{"total":0.25}},"model":"glm-test"}}',
        );
        $this->parser->feed(
            '{"type":"agent_end","messages":[{"role":"assistant","content":[{"type":"text","text":"Large OK"}]}]}',
        );

        $result = $this->parser->result();

        $peakMemoryDelta = memory_get_peak_usage(true) - $memoryBeforeParse;

        self::assertLessThan(8 * 1024 * 1024, $peakMemoryDelta);
        self::assertSame('Large OK', $result['outputText']);
        self::assertSame(123, $result['inputTokens']);
        self::assertSame(45, $result['outputTokens']);
        self::assertSame(7, $result['cacheReadTokens']);
        self::assertSame(3, $result['cacheWriteTokens']);
        self::assertSame(0.25, $result['cost']);
        self::assertSame('glm-test', $result['model']);
        self::assertSame(2, $result['turns']);
    }

    // ──── error-контракт: stopReason:"error" + errorMessage ───────────────

    #[Test]
    public function parseErrorSignalCarriesExactErrorMessageFromIncidentFixture(): void
    {
        // Реальная фикстура инцидента var/sessions/brainstorm/2026-07-01_03-35-24/
        // (step 010, роль system_analyst_sherlock, provider: openai-codex):
        // pi завершается с exit 0, но сообщает об ошибке модели внутри JSONL во
        // всех трёх событиях — message_end / turn_end / agent_end.
        $jsonl = implode("\n", [
            '{"type":"message_end","message":{"role":"assistant","content":[],"provider":"openai-codex","model":"gpt-5.5","usage":{"input":0,"output":0,"totalTokens":0,"cost":{"total":0}},"stopReason":"error","errorMessage":"No API key for provider: openai-codex"}}',
            '{"type":"turn_end","message":{"stopReason":"error","errorMessage":"No API key for provider: openai-codex"},"toolResults":[]}',
            '{"type":"agent_end","messages":[{"role":"assistant","content":[],"stopReason":"error","errorMessage":"No API key for provider: openai-codex"}],"willRetry":false}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertTrue($result['isError']);
        self::assertSame('No API key for provider: openai-codex', $result['errorMessage']);
        // usage-метрики сохраняются и при ошибке модели.
        self::assertSame('gpt-5.5', $result['model']);
        self::assertSame(0, $result['inputTokens']);
        self::assertSame(0, $result['outputTokens']);
    }

    #[Test]
    public function parseErrorSignalWithoutErrorMessageUsesFallbackMessage(): void
    {
        // pi сообщил stopReason:"error", но без errorMessage — берём fallback-сообщение,
        // чтобы上层-оркестратор всё равно видел структурный сигнал ошибки.
        $jsonl = implode("\n", [
            '{"type":"message_end","message":{"role":"assistant","content":[],"usage":{"input":0,"output":0,"cost":{"total":0}},"stopReason":"error"}}',
            '{"type":"agent_end","messages":[{"role":"assistant","content":[],"stopReason":"error"}]}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertTrue($result['isError']);
        self::assertSame(
            'Agent stopped due to model error (stopReason: error).',
            $result['errorMessage'],
        );
    }

    #[Test]
    public function parseErrorSignalOnlyInAgentEndLastAssistantMessage(): void
    {
        // message_end/turn_end пришли без stopReason — ошибка фиксируется только
        // в последнем assistant-сообщении agent_end.
        $jsonl = implode("\n", [
            '{"type":"message_end","message":{"role":"assistant","content":[{"type":"text","text":""}],"usage":{"input":0,"output":0,"cost":{"total":0}}}}',
            '{"type":"agent_end","messages":[{"role":"user","content":[{"type":"text","text":"Hi"}]},{"role":"assistant","content":[],"stopReason":"error","errorMessage":"Upstream 503"}]}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertTrue($result['isError']);
        self::assertSame('Upstream 503', $result['errorMessage']);
    }

    #[Test]
    public function parseErrorSignalWithSnakeCaseFields(): void
    {
        // Альтернативная запись полей: stop_reason / error_message (snake_case).
        $jsonl = implode("\n", [
            '{"type":"message_end","message":{"role":"assistant","content":[],"usage":{"input":0,"output":0,"cost":{"total":0}},"stop_reason":"error","error_message":"Provider rate limited"}}',
            '{"type":"agent_end","messages":[{"role":"assistant","content":[],"stop_reason":"error","error_message":"Provider rate limited"}]}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertTrue($result['isError']);
        self::assertSame('Provider rate limited', $result['errorMessage']);
    }

    #[Test]
    public function parseErrorSignalKeepsFirstExplicitErrorMessage(): void
    {
        // pi дублирует stopReason:"error" в нескольких событиях. Берём первое
        // осмысленное errorMessage и далее его не перезаписываем.
        $jsonl = implode("\n", [
            '{"type":"message_end","message":{"role":"assistant","content":[],"usage":{"input":0,"output":0,"cost":{"total":0}},"stopReason":"error","errorMessage":"First error"}}',
            '{"type":"agent_end","messages":[{"role":"assistant","content":[],"stopReason":"error","errorMessage":"Different second error"}]}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertTrue($result['isError']);
        self::assertSame('First error', $result['errorMessage']);
    }

    #[Test]
    public function parseHappyPathDoesNotFlagError(): void
    {
        // Успешный поток без stopReason:"error" не должен флаговать ошибку.
        $jsonl = implode("\n", [
            '{"type":"message_end","message":{"role":"assistant","content":[{"type":"text","text":"OK"}],"usage":{"input":1,"output":1,"turns":1,"cost":{"total":0.0}}}}',
            '{"type":"agent_end","messages":[{"role":"assistant","content":[{"type":"text","text":"OK"}]}]}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertFalse($result['isError']);
        self::assertSame('', $result['errorMessage']);
    }

    /**
     * @return array{outputText: string, inputTokens: int, outputTokens: int, cacheReadTokens: int, cacheWriteTokens: int, cost: float, model: string|null, turns: int, isError: bool, errorMessage: string}
     */
    private function feedJsonl(string $jsonl): array
    {
        foreach (explode("\n", $jsonl) as $line) {
            $this->parser->feed($line);
        }

        return $this->parser->result();
    }
}
