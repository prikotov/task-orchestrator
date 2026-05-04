<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Service\Chain\Dynamic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Dynamic\FormatDynamicJournalService;

#[CoversClass(FormatDynamicJournalService::class)]
final class FormatDynamicJournalServiceTest extends TestCase
{
    private FormatDynamicJournalService $service;

    #[Override]
    protected function setUp(): void
    {
        $this->service = new FormatDynamicJournalService();
    }

    // ─── formatDiscussionEntry (participant) ───────────────────────────

    #[Test]
    public function formatDiscussionEntryFormatsParticipantText(): void
    {
        $result = $this->service->formatDiscussionEntry('Аналитик', 'Мой анализ проблемы.');

        self::assertSame("\n\n# 👤 Аналитик\n\nМой анализ проблемы.", $result);
    }

    // ─── formatFacilitatorDiscussionEntry — дал слово ──────────────────

    #[Test]
    public function facilitatorDiscussionEntryGivesWordWithChallenge(): void
    {
        $result = $this->service->formatFacilitatorDiscussionEntry(
            facilitatorRole: 'Фасилитатор',
            done: false,
            nextRole: 'Архитектор',
            challenge: 'если Гэндальф видит 5 групп, нужно проверить декомпозицию',
            synthesis: null,
        );

        self::assertStringContainsString('# 🎯 Фасилитатор', $result);
        self::assertStringContainsString('Дал слово Архитектор.', $result);
        self::assertStringContainsString('Вызов: если Гэндальф видит 5 групп, нужно проверить декомпозицию', $result);
    }

    #[Test]
    public function facilitatorDiscussionEntryGivesWordWithoutChallenge(): void
    {
        $result = $this->service->formatFacilitatorDiscussionEntry(
            facilitatorRole: 'Фасилитатор',
            done: false,
            nextRole: 'Тестировщик',
            challenge: null,
            synthesis: null,
        );

        self::assertStringContainsString('Дал слово Тестировщик.', $result);
        self::assertStringNotContainsString('Вызов:', $result);
    }

    #[Test]
    public function facilitatorDiscussionEntryGivesWordWithEmptyChallenge(): void
    {
        $result = $this->service->formatFacilitatorDiscussionEntry(
            facilitatorRole: 'Фасилитатор',
            done: false,
            nextRole: 'Бэкендер',
            challenge: '',
            synthesis: null,
        );

        self::assertStringContainsString('Дал слово Бэкендер.', $result);
        self::assertStringNotContainsString('Вызов:', $result);
    }

    // ─── formatFacilitatorDiscussionEntry — завершил обсуждение ────────

    #[Test]
    public function facilitatorDiscussionEntryDoneWithSynthesis(): void
    {
        $result = $this->service->formatFacilitatorDiscussionEntry(
            facilitatorRole: 'Фасилитатор',
            done: true,
            nextRole: null,
            challenge: null,
            synthesis: 'Общее решение: использовать модульную архитектуру.',
        );

        self::assertStringContainsString(
            'Завершил обсуждение. Synthesis: Общее решение: использовать модульную архитектуру.',
            $result,
        );
    }

    #[Test]
    public function facilitatorDiscussionEntryDoneWithoutSynthesis(): void
    {
        $result = $this->service->formatFacilitatorDiscussionEntry(
            facilitatorRole: 'Фасилитатор',
            done: true,
            nextRole: null,
            challenge: null,
            synthesis: null,
        );

        self::assertStringContainsString('Завершил обсуждение.', $result);
        self::assertStringNotContainsString('Synthesis:', $result);
    }

    // ─── formatFacilitatorDiscussionEntry — edge cases ─────────────────

    #[Test]
    public function facilitatorDiscussionEntryNoDoneNoNextRole(): void
    {
        $result = $this->service->formatFacilitatorDiscussionEntry(
            facilitatorRole: 'Фасилитатор',
            done: false,
            nextRole: null,
            challenge: null,
            synthesis: null,
        );

        self::assertStringContainsString('Ожидание участников.', $result);
    }

    // ─── Формат заголовка ──────────────────────────────────────────────

    #[DataProvider('roleHeaderProvider')]
    #[Test]
    public function participantHeaderUsesPersonEmoji(string $role): void
    {
        $result = $this->service->formatDiscussionEntry($role, 'text');

        self::assertStringContainsString('# 👤 ' . $role, $result);
    }

    #[DataProvider('roleHeaderProvider')]
    #[Test]
    public function facilitatorHeaderUsesTargetEmoji(string $role): void
    {
        $result = $this->service->formatFacilitatorDiscussionEntry(
            facilitatorRole: $role,
            done: false,
            nextRole: 'Архитектор',
            challenge: null,
            synthesis: null,
        );

        self::assertStringContainsString('# 🎯 ' . $role, $result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roleHeaderProvider(): array
    {
        return [
            'facilitator' => ['Фасилитатор'],
            'moderator' => ['Модератор'],
        ];
    }
}
