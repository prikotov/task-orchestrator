<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\FacilitatorResponseParserInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FacilitatorResponseVo;

/**
 * Парсер текстового ответа фасилитатора из LLM.
 *
 * Извлекает JSON из произвольного текста LLM (чистый JSON,
 * markdown-блок ```json ... ```, встроенный в текст) и создаёт
 * соответствующий Value Object.
 *
 * LLM может повторить (echo) system prompt с примерами JSON,
 * поэтому ищется ПОСЛЕДНИЙ валидный JSON-объект в тексте.
 */
final readonly class FacilitatorResponseParserService implements FacilitatorResponseParserInterface
{
    #[Override]
    public function parse(string $llmText): FacilitatorResponseVo
    {
        $json = $this->extractJson($llmText);

        if ($json === null) {
            return FacilitatorResponseVo::createFromDone($llmText);
        }

        if (isset($json['done']) && (bool)$json['done']) {
            return FacilitatorResponseVo::createFromDone(
                $this->normalizeSynthesis($json['synthesis'] ?? null, $llmText),
            );
        }

        if (isset($json['next_role']) && is_string($json['next_role']) && $json['next_role'] !== '') {
            $challenge = isset($json['challenge']) && is_string($json['challenge']) && $json['challenge'] !== ''
                ? $json['challenge']
                : null;

            return FacilitatorResponseVo::createFromNextRole($json['next_role'], $challenge);
        }

        return FacilitatorResponseVo::createFromDone($llmText);
    }

    /**
     * Нормализует значение synthesis из ответа LLM в строку.
     *
     * LLM может вернуть synthesis как массив строк — в этом случае
     * элементы склеиваются через перевод строки.
     *
     * @param mixed $value    Значение поля synthesis из JSON (string|array|null|другое)
     * @param string $fallback Fallback-текст, если synthesis отсутствует
     */
    private function normalizeSynthesis(mixed $value, string $fallback): string
    {
        if ($value === null) {
            return $fallback;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return implode("\n", array_map($this->flattenArrayElement(...), $value));
        }

        return (string)$value;
    }

    /**
     * Приводит элемент массива synthesis к строке.
     *
     * Вложенные массивы рекурсивно склеиваются через перевод строки.
     */
    private function flattenArrayElement(mixed $element): string
    {
        if (is_array($element)) {
            return implode("\n", array_map($this->flattenArrayElement(...), $element));
        }

        return (string)$element;
    }

    /**
     * Извлекает JSON-объект из текста LLM.
     *
     * Поддерживает форматы:
     * - чистый JSON: {"next_role": "architect"}
     * - markdown-блок: ```json\n{"done": true}\n```
     * - JSON внутри текста (несколько вхождений)
     *
     * @return array<string, mixed>|null
     */
    private function extractJson(string $text): ?array
    {
        // Попытка 1: весь текст как JSON
        $decoded = $this->tryDecode($text);
        if ($decoded !== null) {
            return $decoded;
        }

        // Попытка 2: все JSON внутри ```json ... ``` — берём последний
        if (preg_match_all('/```json\s*(\{[^`]*?})\s*```/s', $text, $allMatches) !== false && $allMatches[1] !== []) {
            $reversed = array_reverse($allMatches[1]);
            foreach ($reversed as $match) {
                $decoded = $this->tryDecode($match);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }

        // Попытка 3: все JSON-объекты в тексте — берём последний
        if (preg_match_all('/\{[^{}]*(?:\{[^{}]*}[^{}]*)*}/s', $text, $allMatches) !== false && $allMatches[0] !== []) {
            $reversed = array_reverse($allMatches[0]);
            foreach ($reversed as $match) {
                $decoded = $this->tryDecode($match);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * Пытается декодировать JSON-строку в ассоциативный массив.
     *
     * @return array<string, mixed>|null
     */
    private function tryDecode(string $json): ?array
    {
        try {
            $decoded = json_decode(trim($json), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (is_array($decoded) && !array_is_list($decoded)) {
            return $decoded;
        }

        return null;
    }
}
