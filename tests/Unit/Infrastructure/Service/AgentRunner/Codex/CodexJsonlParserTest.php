<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Codex;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\CodexJsonlParser;

#[CoversClass(CodexJsonlParser::class)]
final class CodexJsonlParserTest extends TestCase
{
    private CodexJsonlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CodexJsonlParser();
    }

    #[Test]
    public function parseTurnCompletedWithAgentMessage(): void
    {
        $jsonl = implode("\n", [
            '{"type":"thread.started","thread_id":"t-001"}',
            '{"type":"turn.started"}',
            '{"type":"turn.completed","turn":{"items":[{"type":"agent_message","content":[{"type":"text","text":"The architecture should use DDD layers."}]}],"usage":{"input_tokens":500,"output_tokens":200,"cached_input_tokens":50}}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('The architecture should use DDD layers.', $result['outputText']);
        self::assertSame(500, $result['inputTokens']);
        self::assertSame(200, $result['outputTokens']);
        self::assertSame(50, $result['cacheReadTokens']);
        self::assertSame(0, $result['cacheWriteTokens']);
        self::assertSame(1, $result['turns']);
    }

    #[Test]
    public function parseTurnCompletedWithCostAndModel(): void
    {
        $jsonl = implode("\n", [
            '{"type":"thread.started","thread_id":"t-002"}',
            '{"type":"turn.started"}',
            '{"type":"turn.completed","turn":{"items":[{"type":"agent_message","content":[{"type":"text","text":"Result text"}]}],"usage":{"input_tokens":100,"output_tokens":50,"cached_input_tokens":10,"cost":0.025},"model":"o3"}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Result text', $result['outputText']);
        self::assertSame(0.025, $result['cost']);
        self::assertSame('o3', $result['model']);
    }

    #[Test]
    public function parseEmptyInput(): void
    {
        $result = $this->parser->result();

        self::assertSame('', $result['outputText']);
        self::assertSame(0, $result['inputTokens']);
        self::assertSame(0, $result['outputTokens']);
        self::assertSame(0, $result['cacheReadTokens']);
        self::assertSame(0, $result['cacheWriteTokens']);
        self::assertSame(0.0, $result['cost']);
        self::assertNull($result['model']);
        self::assertSame(0, $result['turns']);
        self::assertSame(0, $result['reasoningOutputTokens']);
    }

    #[Test]
    public function parseSkipsInvalidJson(): void
    {
        $jsonl = implode("\n", [
            'not json at all',
            '{"type":"turn.completed","turn":{"items":[{"type":"agent_message","content":[{"type":"text","text":"Clean result"}]}],"usage":{"input_tokens":50,"output_tokens":25}}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Clean result', $result['outputText']);
        self::assertSame(50, $result['inputTokens']);
    }

    #[Test]
    public function parseHandlesMissingFieldsGracefully(): void
    {
        $jsonl = '{"type":"turn.completed","turn":{}}';

        $result = $this->feedJsonl($jsonl);

        self::assertSame('', $result['outputText']);
        self::assertSame(0, $result['inputTokens']);
        self::assertSame(0.0, $result['cost']);
        self::assertNull($result['model']);
        self::assertSame(1, $result['turns']);
    }

    #[Test]
    public function parseExtractsLastAgentMessageFromMultipleItems(): void
    {
        // Multi-item turn: command_execution then agent_message
        $jsonl = implode("\n", [
            '{"type":"turn.completed","turn":{"items":['
            . '{"type":"command_execution","command":"ls -la","result":"file1.txt\\nfile2.txt"},'
            . '{"type":"agent_message","content":[{"type":"text","text":"I found 2 files in the directory."}]}'
            . '],"usage":{"input_tokens":300,"output_tokens":150}}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('I found 2 files in the directory.', $result['outputText']);
        self::assertSame(300, $result['inputTokens']);
    }

    #[Test]
    public function parseExtractsLastAgentMessageFromMultipleMessages(): void
    {
        // Multiple agent_messages — should extract the LAST one
        $jsonl = implode("\n", [
            '{"type":"turn.completed","turn":{"items":['
            . '{"type":"agent_message","content":[{"type":"text","text":"First message"}]},'
            . '{"type":"command_execution","command":"cat file.txt","result":"contents"},'
            . '{"type":"agent_message","content":[{"type":"text","text":"Final architectural decision: use CQRS."}]}'
            . '],"usage":{"input_tokens":400,"output_tokens":250}}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Final architectural decision: use CQRS.', $result['outputText']);
    }

    #[Test]
    public function parseAgentMessageWithStringContent(): void
    {
        $jsonl = implode("\n", [
            '{"type":"turn.completed","turn":{"items":[{"type":"agent_message","content":"Plain string response"}],"usage":{"input_tokens":100,"output_tokens":50}}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Plain string response', $result['outputText']);
    }

    #[Test]
    public function parseFallsBackToItemCompleted(): void
    {
        // No turn.completed, only item.completed events
        $jsonl = implode("\n", [
            '{"type":"thread.started","thread_id":"t-003"}',
            '{"type":"turn.started"}',
            '{"type":"item.completed","item":{"type":"agent_message","content":[{"type":"text","text":"Fallback text from item.completed"}]}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Fallback text from item.completed', $result['outputText']);
        self::assertSame(0, $result['inputTokens']);
        self::assertSame(0, $result['turns']);
    }

    #[Test]
    public function parseIgnoresNonAgentMessageItems(): void
    {
        $jsonl = implode("\n", [
            '{"type":"item.completed","item":{"type":"command_execution","command":"ls","result":"files"}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('', $result['outputText']);
    }

    #[Test]
    public function parseTurnCompletedOverwritesItemCompleted(): void
    {
        // item.completed followed by turn.completed — turn.completed should take precedence
        $jsonl = implode("\n", [
            '{"type":"item.completed","item":{"type":"agent_message","content":[{"type":"text","text":"Item text"}]}}',
            '{"type":"turn.completed","turn":{"items":[{"type":"agent_message","content":[{"type":"text","text":"Turn text"}]}],"usage":{"input_tokens":100,"output_tokens":50}}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Turn text', $result['outputText']);
        self::assertSame(100, $result['inputTokens']);
    }

    #[Test]
    public function parseSkipsThreadStarted(): void
    {
        $jsonl = '{"type":"thread.started","thread_id":"t-004"}';

        $result = $this->feedJsonl($jsonl);

        self::assertSame('', $result['outputText']);
        self::assertSame(0, $result['turns']);
    }

    #[Test]
    public function parseSkipsTurnStarted(): void
    {
        $jsonl = '{"type":"turn.started"}';

        $result = $this->feedJsonl($jsonl);

        self::assertSame('', $result['outputText']);
        self::assertSame(0, $result['turns']);
    }

    #[Test]
    public function parseSkipsErrorEvents(): void
    {
        $jsonl = implode("\n", [
            '{"type":"error","message":"Reconnecting... 1/5"}',
            '{"type":"turn.completed","turn":{"items":[{"type":"agent_message","content":[{"type":"text","text":"Recovered"}]}],"usage":{"input_tokens":100,"output_tokens":50}}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Recovered', $result['outputText']);
        self::assertSame(100, $result['inputTokens']);
    }

    #[Test]
    public function parseMultipleTurns(): void
    {
        $jsonl = implode("\n", [
            '{"type":"turn.completed","turn":{"items":[{"type":"agent_message","content":[{"type":"text","text":"First turn"}]}],"usage":{"input_tokens":100,"output_tokens":50,"cached_input_tokens":10,"cost":0.01}}}',
            '{"type":"turn.completed","turn":{"items":[{"type":"agent_message","content":[{"type":"text","text":"Second turn"}]}],"usage":{"input_tokens":200,"output_tokens":100,"cached_input_tokens":20,"cost":0.02}}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        // Last turn.completed should provide the output text
        self::assertSame('Second turn', $result['outputText']);
        self::assertSame(300, $result['inputTokens']); // 100 + 200
        self::assertSame(150, $result['outputTokens']); // 50 + 100
        self::assertSame(30, $result['cacheReadTokens']); // 10 + 20
        self::assertSame(0.03, $result['cost']); // 0.01 + 0.02
        self::assertSame(2, $result['turns']);
    }

    #[Test]
    public function parseAgentMessageWithMultipleContentBlocks(): void
    {
        $jsonl = implode("\n", [
            '{"type":"turn.completed","turn":{"items":[{"type":"agent_message","content":[{"type":"text","text":"Part 1 "},{"type":"text","text":"Part 2"}]}],"usage":{"input_tokens":100,"output_tokens":50}}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Part 1 Part 2', $result['outputText']);
    }

    // ================================================================
    // Codex CLI v0.125.0 формат
    // ================================================================

    #[Test]
    public function parseV1250ItemCompletedWithText(): void
    {
        $jsonl = implode("\n", [
            '{"type":"thread.started","thread_id":"thread_abc"}',
            '{"type":"turn.started"}',
            '{"type":"item.completed","item":{"id":"item_0","type":"agent_message","text":"PING_OK"}}',
            '{"type":"turn.completed","usage":{"input_tokens":26231,"cached_input_tokens":15744,"output_tokens":84,"reasoning_output_tokens":76}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('PING_OK', $result['outputText']);
        self::assertSame(26231, $result['inputTokens']);
        self::assertSame(84, $result['outputTokens']);
        self::assertSame(15744, $result['cacheReadTokens']);
        self::assertSame(1, $result['turns']);
        self::assertSame(76, $result['reasoningOutputTokens']);
    }

    #[Test]
    public function parseV1250TurnCompletedWithTopLevelUsage(): void
    {
        $jsonl = implode("\n", [
            '{"type":"thread.started","thread_id":"thread_xyz"}',
            '{"type":"turn.started"}',
            '{"type":"turn.completed","usage":{"input_tokens":1000,"output_tokens":500,"cached_input_tokens":200}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('', $result['outputText']); // нет item.completed с текстом
        self::assertSame(1000, $result['inputTokens']);
        self::assertSame(500, $result['outputTokens']);
        self::assertSame(200, $result['cacheReadTokens']);
        self::assertSame(1, $result['turns']);
        self::assertSame(0, $result['reasoningOutputTokens']);
    }

    #[Test]
    public function parseV1250FullRealOutput(): void
    {
        // Реальный вывод codex CLI v0.125.0
        $jsonl = implode("\n", [
            '{"type":"thread.started","thread_id":"thread_01"}',
            '{"type":"turn.started"}',
            '{"type":"item.completed","item":{"id":"item_0","type":"agent_message","text":"The answer is 42"}}',
            '{"type":"turn.completed","usage":{"input_tokens":5000,"cached_input_tokens":3000,"output_tokens":120,"reasoning_output_tokens":80}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('The answer is 42', $result['outputText']);
        self::assertSame(5000, $result['inputTokens']);
        self::assertSame(120, $result['outputTokens']);
        self::assertSame(3000, $result['cacheReadTokens']);
        self::assertSame(1, $result['turns']);
        self::assertSame(80, $result['reasoningOutputTokens']);
    }

    #[Test]
    public function parseV1250TurnCompletedOverwritesItemText(): void
    {
        // item.completed с item.text + turn.completed с turn.items — turn.completed перезапишет outputText
        $jsonl = implode("\n", [
            '{"type":"item.completed","item":{"id":"item_0","type":"agent_message","text":"Item text v0.125.0"}}',
            '{"type":"turn.completed","turn":{"items":[{"type":"agent_message","content":[{"type":"text","text":"Turn items text"}]}]},"usage":{"input_tokens":100,"output_tokens":50}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        // turn.completed содержит текст в items — перезапишет item.completed
        self::assertSame('Turn items text', $result['outputText']);
        self::assertSame(100, $result['inputTokens']);
    }

    #[Test]
    public function parseV1250UsageFallbackToTurnUsage(): void
    {
        // Если есть и верхний уровень usage, и turn.usage — верхний приоритет
        $jsonl = implode("\n", [
            '{"type":"turn.completed","usage":{"input_tokens":999,"output_tokens":111},"turn":{"usage":{"input_tokens":100,"output_tokens":50}}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame(999, $result['inputTokens']);
        self::assertSame(111, $result['outputTokens']);
    }

    #[Test]
    public function parseV1250NoUsageAtTopLevelFallsBackToTurnUsage(): void
    {
        // Нет usage на верхнем уровне — fallback на turn.usage
        $jsonl = implode("\n", [
            '{"type":"turn.completed","turn":{"usage":{"input_tokens":200,"output_tokens":80}}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame(200, $result['inputTokens']);
        self::assertSame(80, $result['outputTokens']);
    }

    #[Test]
    public function parseV1250IgnoresItemCompletedWithEmptyText(): void
    {
        // item.text — пустая строка, не должна перезаписать outputText
        $jsonl = implode("\n", [
            '{"type":"item.completed","item":{"id":"item_0","type":"agent_message","text":""}}',
            '{"type":"item.completed","item":{"id":"item_1","type":"agent_message","text":"Real text"}}',
        ]);

        $result = $this->feedJsonl($jsonl);

        self::assertSame('Real text', $result['outputText']);
    }

    #[Test]
    public function feedHandlesCrlfLineEndings(): void
    {
        $this->parser->feed(
            '{"type":"item.completed","item":{"id":"item_0","type":"agent_message","text":"CRLF OK"}}' . "\r",
        );
        $this->parser->feed(
            '{"type":"turn.completed","usage":{"input_tokens":9,"output_tokens":4}}' . "\r",
        );

        $result = $this->parser->result();

        self::assertSame('CRLF OK', $result['outputText']);
        self::assertSame(9, $result['inputTokens']);
        self::assertSame(4, $result['outputTokens']);
    }

    #[Test]
    public function feedInterruptedBeforeTurnCompletedKeepsCompletedItemText(): void
    {
        $this->parser->feed(
            '{"type":"item.completed","item":{"id":"item_0","type":"agent_message","text":"Partial answer"}}',
        );

        $result = $this->parser->result();

        self::assertSame('Partial answer', $result['outputText']);
        self::assertSame(0, $result['inputTokens']);
        self::assertSame(0, $result['turns']);
    }

    #[Test]
    public function resetClearsPreviousState(): void
    {
        $this->parser->feed(
            '{"type":"item.completed","item":{"id":"item_0","type":"agent_message","text":"old"}}',
        );
        $this->parser->reset();

        $result = $this->parser->result();

        self::assertSame('', $result['outputText']);
        self::assertSame(0, $result['inputTokens']);
    }

    #[Test]
    public function feedLargeIncrementalEventsKeepsPeakMemoryBoundedAndExtractsMetrics(): void
    {
        $fillerLine = sprintf(
            '{"type":"item.updated","delta":"%s"}',
            str_repeat('x', 180),
        );
        $iterations = intdiv(12 * 1024 * 1024, strlen($fillerLine)) + 1;

        memory_reset_peak_usage();
        $memoryBeforeParse = memory_get_usage(true);

        for ($i = 0; $i < $iterations; ++$i) {
            $this->parser->feed($fillerLine);
        }
        $this->parser->feed(
            '{"type":"item.completed","item":{"id":"item_0","type":"agent_message","text":"Large OK"}}',
        );
        $this->parser->feed(
            '{"type":"turn.completed","usage":{"input_tokens":321,"output_tokens":54,'
            . '"cached_input_tokens":11,"reasoning_output_tokens":9,"cost":0.75}}',
        );

        $result = $this->parser->result();

        $peakMemoryDelta = memory_get_peak_usage(true) - $memoryBeforeParse;

        self::assertLessThan(8 * 1024 * 1024, $peakMemoryDelta);
        self::assertSame('Large OK', $result['outputText']);
        self::assertSame(321, $result['inputTokens']);
        self::assertSame(54, $result['outputTokens']);
        self::assertSame(11, $result['cacheReadTokens']);
        self::assertSame(0, $result['cacheWriteTokens']);
        self::assertSame(0.75, $result['cost']);
        self::assertSame(1, $result['turns']);
        self::assertSame(9, $result['reasoningOutputTokens']);
    }

    /**
     * @return array{outputText: string, inputTokens: int, outputTokens: int, cacheReadTokens: int, cacheWriteTokens: int, cost: float, model: string|null, turns: int, reasoningOutputTokens: int}
     */
    private function feedJsonl(string $jsonl): array
    {
        foreach (explode("\n", $jsonl) as $line) {
            $this->parser->feed($line);
        }

        return $this->parser->result();
    }
}
