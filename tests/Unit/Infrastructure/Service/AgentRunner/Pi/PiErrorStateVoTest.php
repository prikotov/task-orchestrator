<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Pi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\ValueObject\PiErrorStateVo;

#[CoversClass(PiErrorStateVo::class)]
final class PiErrorStateVoTest extends TestCase
{
    #[Test]
    public function initialStateIsNotErrorAndHasEmptyMessage(): void
    {
        $vo = new PiErrorStateVo();

        self::assertFalse($vo->isError());
        self::assertSame('', $vo->errorMessage());
    }

    #[Test]
    public function nullSignalLeavesStateUnchanged(): void
    {
        // null — stopReason не "error": сигнала ошибки нет, состояние не меняется.
        $vo = (new PiErrorStateVo())->applyErrorSignal(null);

        self::assertFalse($vo->isError());
        self::assertSame('', $vo->errorMessage());
    }

    #[Test]
    public function emptyStringSignalFlagsErrorWithFallbackMessage(): void
    {
        // stopReason:"error" без errorMessage — берём fallback-сообщение.
        $vo = (new PiErrorStateVo())->applyErrorSignal('');

        self::assertTrue($vo->isError());
        self::assertSame(PiErrorStateVo::ERROR_MESSAGE_FALLBACK, $vo->errorMessage());
        self::assertSame(
            'Agent stopped due to model error (stopReason: error).',
            $vo->errorMessage(),
        );
    }

    #[Test]
    public function nonEmptySignalFlagsErrorWithExactMessage(): void
    {
        $vo = (new PiErrorStateVo())->applyErrorSignal('No API key for provider: openai-codex');

        self::assertTrue($vo->isError());
        self::assertSame('No API key for provider: openai-codex', $vo->errorMessage());
    }

    #[Test]
    public function firstExplicitMessageIsKeptAndNotOverwritten(): void
    {
        // pi дублирует stopReason:"error" в нескольких событиях — берём первое
        // осмысленное сообщение и далее его не перезаписываем.
        $vo = (new PiErrorStateVo())
            ->applyErrorSignal('First error')
            ->applyErrorSignal('Different second error');

        self::assertTrue($vo->isError());
        self::assertSame('First error', $vo->errorMessage());
    }

    #[Test]
    public function fallbackIsReplacedByLaterExplicitMessage(): void
    {
        // Сначала пришёл сигнал без текста (fallback), затем осмысленный —
        // осмысленный должен вытеснить fallback, т.к. он ещё не зафиксирован.
        $vo = (new PiErrorStateVo())
            ->applyErrorSignal('')
            ->applyErrorSignal('Real reason');

        self::assertTrue($vo->isError());
        self::assertSame('Real reason', $vo->errorMessage());
    }

    #[Test]
    public function explicitMessageIsNotResetByLaterEmptySignal(): void
    {
        // Зафиксированное осмысленное сообщение не сбрасывается последующим
        // сигналом без текста.
        $vo = (new PiErrorStateVo())
            ->applyErrorSignal('Upstream 503')
            ->applyErrorSignal('');

        self::assertTrue($vo->isError());
        self::assertSame('Upstream 503', $vo->errorMessage());
    }

    #[Test]
    public function repeatedEmptySignalKeepsFallbackMessage(): void
    {
        $vo = (new PiErrorStateVo())
            ->applyErrorSignal('')
            ->applyErrorSignal('');

        self::assertTrue($vo->isError());
        self::assertSame(PiErrorStateVo::ERROR_MESSAGE_FALLBACK, $vo->errorMessage());
    }

    #[Test]
    public function nullSignalAfterErrorKeepsExistingState(): void
    {
        // После зафиксированной ошибки последующие события без сигнала ошибки
        // не сбрасывают состояние.
        $vo = (new PiErrorStateVo())
            ->applyErrorSignal('Failure')
            ->applyErrorSignal(null);

        self::assertTrue($vo->isError());
        self::assertSame('Failure', $vo->errorMessage());
    }

    #[Test]
    public function applyErrorSignalIsImmutableAndReturnsNewInstance(): void
    {
        // VO иммутабельный: applyErrorSignal возвращает новый экземпляр,
        // оригинал не изменяется (требование конвенции VO).
        $original = new PiErrorStateVo();
        $next = $original->applyErrorSignal('boom');

        self::assertNotSame($original, $next);
        self::assertFalse($original->isError());
        self::assertSame('', $original->errorMessage());
        self::assertTrue($next->isError());
        self::assertSame('boom', $next->errorMessage());
    }
}
