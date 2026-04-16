<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Infrastructure\Service\AgentRunner\Pi;

/**
 * Парсер JSONL-потока вывода pi (JSON mode).
 *
 * Извлекает message_end с usage-метриками и текст ассистента.
 */
final readonly class PiJsonlParser
{
    /**
     * Парсит JSONL-поток вывода pi (JSON mode).
     *
     * Извлекает usage-метрики из message_end,
     * а текст ответа — из последнего assistant message в agent_end
     * (чтобы не включать промежуточные tool-вызовы и рассуждения).
     *
     * @return array{outputText: string, inputTokens: int, outputTokens: int, cacheReadTokens: int, cacheWriteTokens: int, cost: float, model: string|null, turns: int}
     */
    public function parse(string $jsonlOutput): array
    {
        $lines = array_filter(explode("\n", trim($jsonlOutput)));
        $inputTokens = $outputTokens = $cacheReadTokens = $cacheWriteTokens = $turns = 0;
        $cost = 0.0;
        $model = null;
        $lastAssistantText = '';
        $hasAgentEnd = false;
        $textDeltas = '';

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $type = $decoded['type'] ?? '';

            if ($type === 'message_end') {
                $metrics = $this->extractUsageMetrics($decoded);
                $inputTokens += $metrics['inputTokens'];
                $outputTokens += $metrics['outputTokens'];
                $turns = $metrics['turns'];
                $cacheReadTokens += $metrics['cacheReadTokens'];
                $cacheWriteTokens += $metrics['cacheWriteTokens'];
                $cost += $metrics['cost'];
                $model = $metrics['model'];
            }

            if ($type === 'agent_end') {
                $hasAgentEnd = true;
                $lastAssistantText = $this->extractLastAssistantText($decoded);
            }

            if ($type === 'message_update') {
                $textDeltas .= $this->extractTextDelta($decoded);
            }
        }

        $outputText = $hasAgentEnd && $lastAssistantText !== ''
            ? $lastAssistantText
            : $textDeltas;

        return [
            'outputText' => $outputText,
            'inputTokens' => $inputTokens,
            'outputTokens' => $outputTokens,
            'cacheReadTokens' => $cacheReadTokens,
            'cacheWriteTokens' => $cacheWriteTokens,
            'cost' => $cost,
            'model' => $model,
            'turns' => $turns,
        ];
    }

    /**
     * Извлекает usage-метрики из message_end.
     *
     * @return array{inputTokens: int, outputTokens: int, turns: int, cacheReadTokens: int, cacheWriteTokens: int, cost: float, model: string|null}
     */
    private function extractUsageMetrics(array $decoded): array
    {
        $message = $decoded['message'] ?? [];
        $usage = $message['usage'] ?? [];
        $cacheInfo = $usage['cache'] ?? [];
        $costInfo = $usage['cost'] ?? [];

        return [
            'inputTokens' => (int) ($usage['input'] ?? 0),
            'outputTokens' => (int) ($usage['output'] ?? 0),
            'turns' => (int) ($usage['turns'] ?? 0),
            'cacheReadTokens' => (int) ($cacheInfo['read'] ?? 0),
            'cacheWriteTokens' => (int) ($cacheInfo['write'] ?? 0),
            'cost' => (float) ($costInfo['total'] ?? 0.0),
            'model' => $message['model'] ?? null,
        ];
    }

    /**
     * Извлекает текст последнего assistant-сообщения из agent_end.
     *
     * Идёт с конца массива messages и ищет последний assistant message с текстом.
     */
    private function extractLastAssistantText(array $decoded): string
    {
        $text = '';
        $messages = $decoded['messages'] ?? [];

        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $msg = $messages[$i];
            if (($msg['role'] ?? '') !== 'assistant') {
                continue;
            }

            $content = $msg['content'] ?? [];
            if (!is_array($content)) {
                continue;
            }

            foreach ($content as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'text') {
                    $text .= $block['text'] ?? '';
                }
            }

            if ($text !== '') {
                break;
            }
        }

        return $text;
    }

    /**
     * Извлекает text_delta из message_update (fallback для single-turn).
     */
    private function extractTextDelta(array $decoded): string
    {
        $event = $decoded['assistantMessageEvent'] ?? [];
        if (($event['type'] ?? '') === 'text_delta') {
            return $event['delta'] ?? '';
        }

        return '';
    }
}
