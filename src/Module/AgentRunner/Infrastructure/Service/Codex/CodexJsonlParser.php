<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex;

/**
 * Парсер JSONL-потока вывода Codex CLI (`codex exec --json`).
 *
 * Извлекает текст ответа из turn.completed (items типа agent_message)
 * и usage-метрики (input_tokens, output_tokens, cached_input_tokens).
 */
final readonly class CodexJsonlParser
{
    /**
     * Парсит JSONL-поток вывода Codex CLI.
     *
     * Формат событий Codex exec --json:
     * - thread.started, turn.started — служебные события
     * - item.started, item.updated, item.completed — элементы ответа
     * - turn.completed — завершение хода с usage и items
     * - turn.failed — ошибка хода
     * - error — промежуточная ошибка
     *
     * @param string $jsonlOutput сырой JSONL-вывод Codex
     *
     * @return array{outputText: string, inputTokens: int, outputTokens: int, cacheReadTokens: int, cacheWriteTokens: int, cost: float, model: string|null, turns: int}
     */
    public function parse(string $jsonlOutput): array
    {
        $lines = array_filter(explode("\n", trim($jsonlOutput)));
        $inputTokens = $outputTokens = $cacheReadTokens = $turns = 0;
        $cost = 0.0;
        $model = null;
        $outputText = '';

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $type = $decoded['type'] ?? '';

            if ($type === 'turn.completed') {
                $turnData = $this->extractTurnCompleted($decoded);
                $inputTokens += $turnData['inputTokens'];
                $outputTokens += $turnData['outputTokens'];
                $cacheReadTokens += $turnData['cacheReadTokens'];
                $cost += $turnData['cost'];
                $model = $turnData['model'];
                ++$turns;

                if ($turnData['outputText'] !== '') {
                    $outputText = $turnData['outputText'];
                }
            }

            if ($type === 'item.completed') {
                $itemText = $this->extractItemText($decoded);
                if ($itemText !== '' && $outputText === '') {
                    $outputText = $itemText;
                }
            }
        }

        return [
            'outputText' => $outputText,
            'inputTokens' => $inputTokens,
            'outputTokens' => $outputTokens,
            'cacheReadTokens' => $cacheReadTokens,
            'cacheWriteTokens' => 0,
            'cost' => $cost,
            'model' => $model,
            'turns' => $turns,
        ];
    }

    /**
     * Извлекает данные из события turn.completed.
     *
     * Структура turn.completed:
     * {
     *   "type": "turn.completed",
     *   "turn": {
     *     "items": [...],
     *     "usage": {
     *       "input_tokens": int,
     *       "output_tokens": int,
     *       "cached_input_tokens": int
     *     }
     *   }
     * }
     *
     * @return array{outputText: string, inputTokens: int, outputTokens: int, cacheReadTokens: int, cost: float, model: string|null}
     */
    private function extractTurnCompleted(array $decoded): array
    {
        $turn = $decoded['turn'] ?? [];
        $usage = $turn['usage'] ?? [];

        $outputText = '';
        $items = $turn['items'] ?? [];
        $outputText = $this->extractLastAgentMessageText($items);

        return [
            'outputText' => $outputText,
            'inputTokens' => (int) ($usage['input_tokens'] ?? 0),
            'outputTokens' => (int) ($usage['output_tokens'] ?? 0),
            'cacheReadTokens' => (int) ($usage['cached_input_tokens'] ?? 0),
            'cost' => (float) ($usage['cost'] ?? 0.0),
            'model' => $turn['model'] ?? null,
        ];
    }

    /**
     * Извлекает текст последнего agent_message из массива items.
     *
     * Идёт с конца массива items и ищет последний элемент типа agent_message
     * с текстовым content.
     *
     * @param list<array> $items массив items из turn.completed
     */
    private function extractLastAgentMessageText(array $items): string
    {
        $text = '';

        for ($i = count($items) - 1; $i >= 0; $i--) {
            $item = $items[$i];

            if (($item['type'] ?? '') !== 'agent_message') {
                continue;
            }

            $content = $item['content'] ?? [];
            if (is_array($content)) {
                foreach ($content as $block) {
                    if (is_array($block) && ($block['type'] ?? '') === 'text') {
                        $text .= $block['text'] ?? '';
                    }
                }
            } elseif (is_string($content)) {
                $text .= $content;
            }

            if ($text !== '') {
                break;
            }
        }

        return $text;
    }

    /**
     * Извлекает текст из события item.completed.
     *
     * Используется как fallback, если turn.completed не содержит items.
     *
     * @return string текст элемента или пустая строка
     */
    private function extractItemText(array $decoded): string
    {
        $item = $decoded['item'] ?? [];

        if (($item['type'] ?? '') !== 'agent_message') {
            return '';
        }

        $content = $item['content'] ?? [];
        $text = '';

        if (is_array($content)) {
            foreach ($content as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'text') {
                    $text .= $block['text'] ?? '';
                }
            }
        } elseif (is_string($content)) {
            $text .= $content;
        }

        return $text;
    }
}
