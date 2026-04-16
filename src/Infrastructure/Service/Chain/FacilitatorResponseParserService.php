<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Infrastructure\Service\Chain;

use TasK\Orchestrator\Domain\Service\Chain\FacilitatorResponseParserInterface;
use TasK\Orchestrator\Domain\ValueObject\FacilitatorResponseVo;
use Override;

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
            return FacilitatorResponseVo::createFromDone((string)($json['synthesis'] ?? $llmText));
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
