<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain\FacilitatorResponseParserService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FacilitatorResponseParserService::class)]
final class FacilitatorResponseParserServiceTest extends TestCase
{
    private FacilitatorResponseParserService $parser;

    protected function setUp(): void
    {
        $this->parser = new FacilitatorResponseParserService();
    }

    #[Test]
    public function parseFromPureJsonNextRole(): void
    {
        $vo = $this->parser->parse('{"next_role": "marketer"}');

        self::assertFalse($vo->isDone());
        self::assertSame('marketer', $vo->getNextRole());
    }

    #[Test]
    public function parseFromPureJsonDone(): void
    {
        $vo = $this->parser->parse('{"done": true, "synthesis": "Great ideas!"}');

        self::assertTrue($vo->isDone());
        self::assertSame('Great ideas!', $vo->getSynthesis());
    }

    #[Test]
    public function parseFromMarkdownJsonBlock(): void
    {
        $text = "Here is my decision:\n```json\n{\"next_role\": \"backend_developer\"}\n```\nLet him speak.";
        $vo = $this->parser->parse($text);

        self::assertFalse($vo->isDone());
        self::assertSame('backend_developer', $vo->getNextRole());
    }

    #[Test]
    public function parseFromEmbeddedJson(): void
    {
        $text = 'I think the next speaker should be {"next_role": "sales_manager"} for this topic.';
        $vo = $this->parser->parse($text);

        self::assertFalse($vo->isDone());
        self::assertSame('sales_manager', $vo->getNextRole());
    }

    #[Test]
    public function parseFromInvalidTextReturnsDone(): void
    {
        $text = 'I cannot provide a structured response right now.';
        $vo = $this->parser->parse($text);

        self::assertTrue($vo->isDone());
        self::assertSame($text, $vo->getSynthesis());
    }

    #[Test]
    public function parseFromDoneWithoutSynthesisReturnsOriginalText(): void
    {
        $text = '{"done": true}';
        $vo = $this->parser->parse($text);

        self::assertTrue($vo->isDone());
        self::assertSame($text, $vo->getSynthesis());
    }

    #[Test]
    public function parseFromEmptyNextRoleReturnsDone(): void
    {
        $text = '{"next_role": ""}';
        $vo = $this->parser->parse($text);

        self::assertTrue($vo->isDone());
    }

    #[Test]
    public function parseFromJsonWithExtraFields(): void
    {
        $text = '{"next_role": "architect", "confidence": 0.9}';
        $vo = $this->parser->parse($text);

        self::assertFalse($vo->isDone());
        self::assertSame('architect', $vo->getNextRole());
    }

    #[Test]
    public function parseFromDoneFalseWithNextRole(): void
    {
        $text = '{"done": false, "next_role": "marketer"}';
        $vo = $this->parser->parse($text);

        self::assertFalse($vo->isDone());
        self::assertSame('marketer', $vo->getNextRole());
    }

    #[Test]
    public function parseFromJsonWithChallenge(): void
    {
        $text = '{"next_role": "architect", "challenge": "Your claim about microservices is wrong"}';
        $vo = $this->parser->parse($text);

        self::assertFalse($vo->isDone());
        self::assertSame('architect', $vo->getNextRole());
        self::assertSame('Your claim about microservices is wrong', $vo->getChallenge());
    }

    #[Test]
    public function parseFromJsonWithEmptyChallengeReturnsNull(): void
    {
        $text = '{"next_role": "architect", "challenge": ""}';
        $vo = $this->parser->parse($text);

        self::assertNull($vo->getChallenge());
    }

    #[Test]
    public function parseFromEchoedPromptWithRealResponsePicksLast(): void
    {
        // LLM echo'd the system prompt with an example JSON, then gave its real answer
        $text = "ПРАВИЛА:\n1. Проанализируй\n\n"
            . "ответ: {\"next_role\": \"architect\"}\n"   // echo'd example — must be ignored
            . "[Задача]: Тестовая тема"
            . '{"next_role": "system_architect"}';  // real answer — must win

        $vo = $this->parser->parse($text);

        self::assertFalse($vo->isDone());
        self::assertSame('system_architect', $vo->getNextRole());
    }

    #[Test]
    public function parseFromMultipleJsonBlocksPicksLastValid(): void
    {
        $text = "```json\n{\"next_role\": \"architect\"}\n```\n\n"
            . "Some reasoning text\n\n"
            . "```json\n{\"next_role\": \"marketer\"}\n```";

        $vo = $this->parser->parse($text);

        self::assertFalse($vo->isDone());
        self::assertSame('marketer', $vo->getNextRole());
    }

    public static function llmResponseProvider(): array
    {
        return [
            'pure JSON next_role' => ['{"next_role": "architect"}', false, 'architect', null],
            'pure JSON done' => ['{"done": true, "synthesis": "ok"}', true, null, 'ok'],
            'markdown block' => ["```json\n{\"next_role\": \"backend_developer\"}\n```", false, 'backend_developer', null],
            'random text' => ['Just some text', true, null, 'Just some text'],
            'done false empty role' => ['{"done": false, "next_role": ""}', true, null, '{"done": false, "next_role": ""}'],
        ];
    }

    #[DataProvider('llmResponseProvider')]
    #[Test]
    public function parseFromVariousLLMResponses(
        string $text,
        bool $expectedDone,
        ?string $expectedNextRole,
        ?string $expectedSynthesis,
    ): void {
        $vo = $this->parser->parse($text);

        self::assertSame($expectedDone, $vo->isDone());
        self::assertSame($expectedNextRole, $vo->getNextRole());
        self::assertSame($expectedSynthesis, $vo->getSynthesis());
    }
}
