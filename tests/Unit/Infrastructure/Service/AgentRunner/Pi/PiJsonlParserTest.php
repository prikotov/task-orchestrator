<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Pi;

use TasK\Orchestrator\Infrastructure\Service\AgentRunner\Pi\PiJsonlParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

        $result = $this->parser->parse($jsonl);

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
        $result = $this->parser->parse('');

        self::assertSame('', $result['outputText']);
        self::assertSame(0, $result['inputTokens']);
        self::assertEqualsWithDelta(0.0, $result['cost'], 0.0001);
    }

    #[Test]
    public function parseSkipsInvalidJson(): void
    {
        $jsonl = "not json\n{\"type\":\"message_end\",\"message\":{\"role\":\"assistant\",\"content\":[{\"type\":\"text\",\"text\":\"Result\"}],\"usage\":{\"input\":50,\"output\":25,\"turns\":1,\"cache\":{\"read\":0,\"write\":0},\"cost\":{\"total\":0.005}},\"model\":\"test\"}}\n{\"type\":\"agent_end\",\"messages\":[{\"role\":\"user\",\"content\":[{\"type\":\"text\",\"text\":\"Hi\"}]},{\"role\":\"assistant\",\"content\":[{\"type\":\"text\",\"text\":\"Result\"}]}]}";

        $result = $this->parser->parse($jsonl);

        self::assertSame('Result', $result['outputText']);
        self::assertSame(50, $result['inputTokens']);
    }

    #[Test]
    public function parseHandlesMissingFieldsGracefully(): void
    {
        $jsonl = '{"type":"message_end","message":{}}';

        $result = $this->parser->parse($jsonl);

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

        $result = $this->parser->parse($jsonl);

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

        $result = $this->parser->parse($jsonl);

        self::assertSame('Part1 Part2', $result['outputText']);
    }
}
