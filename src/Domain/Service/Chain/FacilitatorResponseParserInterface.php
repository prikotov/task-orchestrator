<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\ValueObject\FacilitatorResponseVo;

/**
 * Контракт парсинга текстового ответа фасилитатора из LLM.
 *
 * Инкапсулирует логику извлечения JSON из произвольного текста LLM
 * и создания соответствующего Value Object.
 */
interface FacilitatorResponseParserInterface
{
    /**
     * Парсит текстовый ответ LLM-фасилитатора в Value Object.
     *
     * Поддерживаемые форматы ответа:
     * - {next_role: "architect"} — дать слово участнику
     * - {done: true, synthesis: "..."} — завершить brainstorm
     * - произвольный текст без JSON — считается done с текстом как synthesis
     */
    public function parse(string $llmText): FacilitatorResponseVo;
}
