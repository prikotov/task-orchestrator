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

    /**
     * @return array{outputText: string, inputTokens: int, outputTokens: int, cacheReadTokens: int, cacheWriteTokens: int, cost: float, model: string|null, turns: int}
     */
    private function feedJsonl(string $jsonl): array
    {
        foreach (explode("\n", $jsonl) as $line) {
            $this->parser->feed($line);
        }

        return $this->parser->result();
    }
}
