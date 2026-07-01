<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex;

/**
 * Stateful-парсер JSONL-потока вывода Codex CLI (`codex exec --json`).
 *
 * Принимает уже выделенные строки через feed() и хранит только итоговый текст/usage.
 */
final class CodexJsonlParser
{
    private int $inputTokens = 0;
    private int $outputTokens = 0;
    private int $cacheReadTokens = 0;
    private int $reasoningOutputTokens = 0;
    private int $turns = 0;
    private float $cost = 0.0;
    private ?string $model = null;
    private string $outputText = '';

    /**
     * Сбрасывает состояние перед новым потоком JSONL.
     */
    public function reset(): void
    {
        $this->inputTokens = 0;
        $this->outputTokens = 0;
        $this->cacheReadTokens = 0;
        $this->reasoningOutputTokens = 0;
        $this->turns = 0;
        $this->cost = 0.0;
        $this->model = null;
        $this->outputText = '';
    }

    /**
     * Обрабатывает одну JSONL-строку.
     */
    public function feed(string $line): void
    {
        $line = rtrim($line, "\r");
        if (trim($line) === '') {
            return;
        }

        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            return;
        }

        $type = $decoded['type'] ?? '';
        if ($type === 'turn.completed') {
            $this->applyTurnCompleted($decoded);
            return;
        }

        if ($type === 'item.completed') {
            $this->applyItemCompleted($decoded);
        }
    }

    /**
     * Возвращает результат в прежнем shape массива.
     *
     * @return array{outputText: string, inputTokens: int, outputTokens: int, cacheReadTokens: int, cacheWriteTokens: int, cost: float, model: string|null, turns: int, reasoningOutputTokens: int}
     */
    public function result(): array
    {
        return [
            'outputText' => $this->outputText,
            'inputTokens' => $this->inputTokens,
            'outputTokens' => $this->outputTokens,
            'cacheReadTokens' => $this->cacheReadTokens,
            'cacheWriteTokens' => 0,
            'cost' => $this->cost,
            'model' => $this->model,
            'turns' => $this->turns,
            'reasoningOutputTokens' => $this->reasoningOutputTokens,
        ];
    }

    /**
     * Извлекает usage из turn.completed и текст из turn.items старого формата.
     *
     * @param array<string, mixed> $decoded
     */
    private function applyTurnCompleted(array $decoded): void
    {
        $turn = $decoded['turn'] ?? [];
        if (!is_array($turn)) {
            $turn = [];
        }

        $usage = $decoded['usage'] ?? $turn['usage'] ?? [];
        if (!is_array($usage)) {
            $usage = [];
        }

        $this->inputTokens += (int) ($usage['input_tokens'] ?? 0);
        $this->outputTokens += (int) ($usage['output_tokens'] ?? 0);
        $this->cacheReadTokens += (int) ($usage['cached_input_tokens'] ?? 0);
        $this->reasoningOutputTokens += (int) ($usage['reasoning_output_tokens'] ?? 0);
        $this->cost += (float) ($usage['cost'] ?? 0.0);
        ++$this->turns;

        if (isset($turn['model']) && is_string($turn['model'])) {
            $this->model = $turn['model'];
        }

        $items = $turn['items'] ?? [];
        if (!is_array($items)) {
            return;
        }

        $turnOutputText = $this->extractLastAgentMessageText($items);
        if ($turnOutputText !== '') {
            $this->outputText = $turnOutputText;
        }
    }

    /**
     * Извлекает текст из item.completed.
     *
     * @param array<string, mixed> $decoded
     */
    private function applyItemCompleted(array $decoded): void
    {
        $itemText = $this->extractItemText($decoded);
        if ($itemText === '') {
            return;
        }

        $this->outputText = $itemText;
    }

    /**
     * Извлекает текст последнего agent_message из массива items.
     *
     * @param array<int, mixed> $items массив items из turn.completed
     */
    private function extractLastAgentMessageText(array $items): string
    {
        for ($i = count($items) - 1; $i >= 0; --$i) {
            $item = $items[$i];
            if (!is_array($item)) {
                continue;
            }

            if (($item['type'] ?? '') !== 'agent_message') {
                continue;
            }

            $text = $this->extractTextFromItem($item);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * Извлекает текст из события item.completed.
     *
     * @param array<string, mixed> $decoded
     */
    private function extractItemText(array $decoded): string
    {
        $item = $decoded['item'] ?? [];
        if (!is_array($item)) {
            return '';
        }

        if (($item['type'] ?? '') !== 'agent_message') {
            return '';
        }

        return $this->extractTextFromItem($item);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extractTextFromItem(array $item): string
    {
        if (isset($item['text']) && is_string($item['text']) && $item['text'] !== '') {
            return $item['text'];
        }

        $content = $item['content'] ?? [];
        if (is_string($content)) {
            return $content;
        }

        if (!is_array($content)) {
            return '';
        }

        $text = '';
        foreach ($content as $block) {
            if (!is_array($block)) {
                continue;
            }

            if (($block['type'] ?? '') !== 'text') {
                continue;
            }

            if (isset($block['text']) && is_string($block['text'])) {
                $text .= $block['text'];
            }
        }

        return $text;
    }
}
