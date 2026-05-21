<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Codex;

/**
 * Парсер JSONL-потока вывода Codex CLI (`codex exec --json`).
 *
 * Извлекает текст ответа из item.completed (item.text или item.content[])
 * и usage-метрики (input_tokens, output_tokens, cached_input_tokens, reasoning_output_tokens).
 *
 * Поддерживаемые форматы:
 * - codex CLI >= v0.125.0: item.text — строка, usage — на верхнем уровне turn.completed
 * - Старый формат: item.content[] — массив блоков, usage — внутри turn.usage
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
     * @return array{outputText: string, inputTokens: int, outputTokens: int, cacheReadTokens: int, cacheWriteTokens: int, cost: float, model: string|null, turns: int, reasoningOutputTokens: int}
     */
    public function parse(string $jsonlOutput): array
    {
        $lines = array_filter(explode("\n", trim($jsonlOutput)));
        $inputTokens = $outputTokens = $cacheReadTokens = $reasoningOutputTokens = $turns = 0;
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
                $reasoningOutputTokens += $turnData['reasoningOutputTokens'];
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
            'reasoningOutputTokens' => $reasoningOutputTokens,
        ];
    }

    /**
     * Извлекает данные из события turn.completed.
     *
     * Codex CLI >= v0.125.0 кладёт usage на верхний уровень:
     * {
     *   "type": "turn.completed",
     *   "usage": {
     *     "input_tokens": int,
     *     "output_tokens": int,
     *     "cached_input_tokens": int,
     *     "reasoning_output_tokens": int
     *   }
     * }
     *
     * Старый формат — usage внутри turn:
     * {
     *   "type": "turn.completed",
     *   "turn": {
     *     "items": [...],
     *     "usage": { ... }
     *   }
     * }
     *
     * @return array{outputText: string, inputTokens: int, outputTokens: int, cacheReadTokens: int, reasoningOutputTokens: int, cost: float, model: string|null}
     */
    private function extractTurnCompleted(array $decoded): array
    {
        $turn = $decoded['turn'] ?? [];

        // Codex v0.125.0+: usage на верхнем уровне; старый формат — внутри turn
        $usage = $decoded['usage'] ?? $turn['usage'] ?? [];

        $items = $turn['items'] ?? [];
        $outputText = $this->extractLastAgentMessageText($items);

        return [
            'outputText' => $outputText,
            'inputTokens' => (int) ($usage['input_tokens'] ?? 0),
            'outputTokens' => (int) ($usage['output_tokens'] ?? 0),
            'cacheReadTokens' => (int) ($usage['cached_input_tokens'] ?? 0),
            'reasoningOutputTokens' => (int) ($usage['reasoning_output_tokens'] ?? 0),
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
     * Codex CLI >= v0.125.0 передаёт текст как item.text (строка):
     * {
     *   "type": "item.completed",
     *   "item": {"type": "agent_message", "text": "PING_OK"}
     * }
     *
     * Старый формат — массив блоков content[]:
     * {
     *   "type": "item.completed",
     *   "item": {"type": "agent_message", "content": [{"type": "text", "text": "..."}]}
     * }
     *
     * @return string текст элемента или пустая строка
     */
    private function extractItemText(array $decoded): string
    {
        $item = $decoded['item'] ?? [];

        if (($item['type'] ?? '') !== 'agent_message') {
            return '';
        }

        // Codex v0.125.0+: item.text — строка
        if (isset($item['text']) && is_string($item['text']) && $item['text'] !== '') {
            return $item['text'];
        }

        // Старый формат: item.content[] — массив блоков или строка
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
