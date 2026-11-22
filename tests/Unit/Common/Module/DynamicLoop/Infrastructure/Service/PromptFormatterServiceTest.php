<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\DynamicLoop\Infrastructure\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service\PromptFormatterService;

#[CoversClass(PromptFormatterService::class)]
final class PromptFormatterServiceTest extends TestCase
{
    private const string CONTINUE_PROMPT = "Тема: %s\n\nЖурнал:\n%s\n\nФайлы:\n%s\n%s\n\nПравила:";

    private const string START_PROMPT = "Начало по теме: %s\n\nОтветь JSON.";

    private PromptFormatterService $service;

    protected function setUp(): void
    {
        $this->service = new PromptFormatterService();
    }

    // --- buildFacilitatorContext: continue-ветка (есть журнал/файлы) ---

    #[Test]
    public function continuePromptWithAddendumRendersLeadBlock(): void
    {
        $result = $this->service->buildFacilitatorContext(
            startPrompt: self::START_PROMPT,
            continuePrompt: self::CONTINUE_PROMPT,
            topic: 'Дизайн кэша',
            facilitatorSummary: 'Журнал раундов 1-3',
            responseFilesList: '- architect: f3.md',
            taskAddendum: 'Появились скриншоты, дайте слово участникам',
        );

        self::assertSame(
            "Тема: Дизайн кэша\n\nЖурнал:\nЖурнал раундов 1-3\n\nФайлы:\n- architect: f3.md\n\n"
            . "[Дополнение от Лида — новая вводная]:\nПоявились скриншоты, дайте слово участникам\n\nПравила:",
            $result,
        );
    }

    #[Test]
    public function continuePromptWithoutAddendumDoesNotRenderBlock(): void
    {
        $result = $this->service->buildFacilitatorContext(
            startPrompt: self::START_PROMPT,
            continuePrompt: self::CONTINUE_PROMPT,
            topic: 'Дизайн кэша',
            facilitatorSummary: 'Журнал',
            responseFilesList: '- architect: f3.md',
            taskAddendum: null,
        );

        self::assertStringNotContainsString('Дополнение от Лида', $result);
        // Рендеринг идентичен прежнему: без лишних пустых строк на месте блока.
        self::assertSame("Тема: Дизайн кэша\n\nЖурнал:\nЖурнал\n\nФайлы:\n- architect: f3.md\n\nПравила:", $result);
    }

    #[Test]
    public function continuePromptWithWhitespaceAddendumDoesNotRenderBlock(): void
    {
        $result = $this->service->buildFacilitatorContext(
            startPrompt: self::START_PROMPT,
            continuePrompt: self::CONTINUE_PROMPT,
            topic: 'Тема',
            facilitatorSummary: 'Журнал',
            responseFilesList: '',
            taskAddendum: "   \n\t ",
        );

        self::assertStringNotContainsString('Дополнение от Лида', $result);
    }

    #[Test]
    public function continuePromptWithLegacyTemplateRendersAsBefore(): void
    {
        // Легаси-шаблон из трёх плейсхолдеров: дополнение передаётся четвёртым
        // аргументом sprintf и игнорируется — вывод байт-в-байт как прежде.
        $legacyTemplate = 'Cont: %s %s %s';

        $result = $this->service->buildFacilitatorContext(
            startPrompt: self::START_PROMPT,
            continuePrompt: $legacyTemplate,
            topic: 'Тема',
            facilitatorSummary: 'Журнал',
            responseFilesList: '',
            taskAddendum: 'Новое дополнение',
        );

        self::assertSame('Cont: Тема Журнал ', $result);
    }

    // --- buildFacilitatorContext: start-ветка (нет журнала и файлов) ---

    #[Test]
    public function startPromptWithAddendumAppendsBlock(): void
    {
        $result = $this->service->buildFacilitatorContext(
            startPrompt: self::START_PROMPT,
            continuePrompt: self::CONTINUE_PROMPT,
            topic: 'Дизайн кэша',
            facilitatorSummary: '',
            responseFilesList: '',
            taskAddendum: 'Новая вводная от Лида',
        );

        self::assertStringStartsWith('Начало по теме: Дизайн кэша', $result);
        self::assertStringContainsString("\n\n[Дополнение от Лида — новая вводная]:\nНовая вводная от Лида", $result);
    }

    #[Test]
    public function startPromptWithoutAddendumIsUnchanged(): void
    {
        $result = $this->service->buildFacilitatorContext(
            startPrompt: self::START_PROMPT,
            continuePrompt: self::CONTINUE_PROMPT,
            topic: 'Дизайн кэша',
            facilitatorSummary: '',
            responseFilesList: '',
            taskAddendum: null,
        );

        self::assertSame("Начало по теме: Дизайн кэша\n\nОтветь JSON.", $result);
    }

    // --- buildFacilitatorContext: нормализация текста дополнения ---

    #[Test]
    public function addendumTextIsTrimmedInBlock(): void
    {
        $result = $this->service->buildFacilitatorContext(
            startPrompt: self::START_PROMPT,
            continuePrompt: self::CONTINUE_PROMPT,
            topic: 'Тема',
            facilitatorSummary: 'Журнал',
            responseFilesList: '',
            taskAddendum: "  Текст с пробелами по краям  ",
        );

        self::assertStringContainsString("[Дополнение от Лида — новая вводная]:\nТекст с пробелами по краям\n\nПравила:", $result);
    }

    #[Test]
    public function continuePromptCollapsesExtraBlankLinesAroundBlock(): void
    {
        $result = $this->service->buildFacilitatorContext(
            startPrompt: self::START_PROMPT,
            continuePrompt: self::CONTINUE_PROMPT,
            topic: 'Тема',
            facilitatorSummary: 'Журнал',
            responseFilesList: '',
            taskAddendum: 'Дополнение',
        );

        self::assertDoesNotMatchRegularExpression('/\n{3,}/', $result);
    }
}
