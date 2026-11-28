<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopSessionStateVo;

/**
 * Контракт чтения состояния сессии dynamic-цикла.
 */
interface DynamicLoopSessionReaderInterface
{
    public function getResumedState(): ?DynamicLoopSessionStateVo;

    /**
     * @return list<array{role: string, path: string}>
     */
    public function getResponseFilePaths(int $upToStep): array;

    /**
     * Возвращает записи раундов сессии в порядке их записи.
     *
     * Ключи step — внутренняя деталь хранения сессии; публичный контракт
     * возвращает упорядоченный список значений (list) с полной фактической
     * формой записи, которую формирует Infrastructure-реализация сессии.
     *
     * Каноническая форма записи остаётся закрытой; завершающий `...`
     * допускается только в принимающей форме потребителя
     * (см. restoreFromRoundFiles()).
     *
     * @return list<array{system: string, user: string, role: string, is_facilitator: bool, round: int, duration: float, input_tokens: int, output_tokens: int, cost: float, model: string|null, provider: string|null, thinking: string|null, cache_read_tokens: int, cache_write_tokens: int, response?: string, error?: string, error_message?: string, invocation?: string}>
     */
    public function getRoundFiles(): array;
}
