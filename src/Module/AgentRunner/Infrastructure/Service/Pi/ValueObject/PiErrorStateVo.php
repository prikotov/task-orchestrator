<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\ValueObject;

/**
 * Immutable Value Object состояния ошибки модели pi.
 *
 * Инкапсулирует распознанный сигнал stopReason:"error" + errorMessage и применяет
 * инвариант: берём первое осмысленное (непустое) errorMessage и далее его не
 * перезаписываем; если сигнал пришёл без текста — используем fallback-сообщение.
 *
 * Каждая операция возвращает новый экземпляр (immutable), как и требует конвенция VO.
 *
 * Знание формата pi-событий (message_end/turn_end/agent_end) здесь не хранится —
 * парсер извлекает сигнал самостоятельно и передаёт в applyErrorSignal() уже
 * примитивное значение, что делает этот VO чистым инвариантом.
 */
// phpcs:ignore PrikotovCodingStandard.Structure.ServiceStructure.NoServiceSuffix -- VO nested by convention.
final readonly class PiErrorStateVo
{
    /**
     * Текст ошибки по умолчанию, когда pi сообщает stopReason:"error", но без errorMessage.
     */
    public const string ERROR_MESSAGE_FALLBACK = 'Agent stopped due to model error (stopReason: error).';

    public function __construct(
        private bool $hasError = false,
        private string $errorMessage = '',
        private bool $hasExplicitErrorMessage = false,
    ) {
    }

    /**
     * Применяет инвариант к извлечённому сигналу ошибки и возвращает новое состояние.
     *
     * @param string|null $errorMessage null — stopReason не "error" (сигнала нет);
     *        пустая строка — stopReason "error", но без текста;
     *        непустая строка — текст ошибки модели.
     *
     * @return self новое состояние; текущий экземпляр без изменений, если сигнал
     *         игнорируется (уже зафиксировано осмысленное сообщение или сигнала нет).
     */
    public function applyErrorSignal(?string $errorMessage): self
    {
        if ($this->hasExplicitErrorMessage || $errorMessage === null) {
            return $this;
        }

        if ($errorMessage !== '') {
            return new self(
                hasError: true,
                errorMessage: $errorMessage,
                hasExplicitErrorMessage: true,
            );
        }

        // stopReason:error без текста — фиксируем ошибку, текст берём из fallback,
        // если ещё ничего осмысленного не задано (иначе сохраняем прежний).
        return new self(
            hasError: true,
            errorMessage: $this->errorMessage === '' ? self::ERROR_MESSAGE_FALLBACK : $this->errorMessage,
            hasExplicitErrorMessage: false,
        );
    }

    public function isError(): bool
    {
        return $this->hasError;
    }

    public function errorMessage(): string
    {
        return $this->errorMessage;
    }
}
