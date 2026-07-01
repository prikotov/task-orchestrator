<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi;

use RuntimeException;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\ValueObject\PiErrorStateVo;

/**
 * Stateful-парсер JSONL-потока вывода pi (JSON mode).
 *
 * Принимает уже выделенные строки через feed() и хранит только итоговый текст/usage.
 *
 * Распознаёт ошибки модели: pi при сбое провайдера (нет API-ключа, 5xx, лимиты) завершается
 * с exit 0, но сообщает об ошибке внутри JSONL полями stopReason:"error" + errorMessage:"..."
 * в событиях message_end / turn_end / agent_end. Эти поля сохраняются в result() как
 * isError + errorMessage и прокидываются в AgentResultVo::createError().
 */
final class PiJsonlParser
{
    private const int TEXT_DELTA_FALLBACK_MEMORY_LIMIT_BYTES = 1048576;

    private int $inputTokens = 0;
    private int $outputTokens = 0;
    private int $cacheReadTokens = 0;
    private int $cacheWriteTokens = 0;
    private int $turns = 0;
    private float $cost = 0.0;
    private ?string $model = null;
    private string $lastAssistantText = '';
    private bool $hasAgentEnd = false;

    /**
     * Состояние ошибки модели (stopReason:"error" + errorMessage): инкапсулировано
     * в иммутабельном VO, что убирает три раздельных поля и держит счётчик полей
     * класса под лимитом PHPMD TooManyFields. Инвариант (приоритет первого
     * осмысленного сообщения + fallback) живёт внутри VO.
     */
    private PiErrorStateVo $errorState;

    /** @var resource|null */
    private mixed $textDeltaFallbackStream = null;

    public function __construct()
    {
        // new в инициализаторе свойства не разрешён PHP, поэтому начальное
        // состояние ошибки задаётся в конструкторе.
        $this->errorState = new PiErrorStateVo();
    }

    /**
     * Сбрасывает состояние перед новым потоком JSONL.
     */
    public function reset(): void
    {
        $this->closeTextDeltaFallbackStream();
        $this->inputTokens = 0;
        $this->outputTokens = 0;
        $this->cacheReadTokens = 0;
        $this->cacheWriteTokens = 0;
        $this->turns = 0;
        $this->cost = 0.0;
        $this->model = null;
        $this->lastAssistantText = '';
        $this->hasAgentEnd = false;
        $this->errorState = new PiErrorStateVo();
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
        if ($type === 'message_end') {
            $this->applyUsageMetrics($decoded);
            $this->applyErrorSignal($decoded);
            return;
        }

        if ($type === 'turn_end') {
            $this->applyErrorSignal($decoded);
            return;
        }

        if ($type === 'agent_end') {
            $this->hasAgentEnd = true;
            $this->lastAssistantText = $this->extractLastAssistantText($decoded);
            $this->applyErrorSignal($decoded);
            $this->closeTextDeltaFallbackStream();
            return;
        }

        if ($type === 'message_update') {
            $this->appendTextDeltaFallback($decoded);
        }
    }

    /**
     * Возвращает итоговый результат парсинга потока.
     *
     * Ключи isError и errorMessage — additive: они не удаляют существующие ключи.
     *
     * @return array{outputText: string, inputTokens: int, outputTokens: int, cacheReadTokens: int, cacheWriteTokens: int, cost: float, model: string|null, turns: int, isError: bool, errorMessage: string}
     */
    public function result(): array
    {
        $outputText = $this->lastAssistantText;
        if (!$this->hasAgentEnd) {
            $outputText = $this->readTextDeltaFallback();
        }

        return [
            'outputText' => $outputText,
            'inputTokens' => $this->inputTokens,
            'outputTokens' => $this->outputTokens,
            'cacheReadTokens' => $this->cacheReadTokens,
            'cacheWriteTokens' => $this->cacheWriteTokens,
            'cost' => $this->cost,
            'model' => $this->model,
            'turns' => $this->turns,
            'isError' => $this->errorState->isError(),
            'errorMessage' => $this->errorState->errorMessage(),
        ];
    }

    /**
     * Фиксирует сигнал ошибки модели из события message_end/turn_end/agent_end.
     *
     * pi дублирует stopReason:"error" + errorMessage сразу в нескольких событиях.
     * Берём первое осмысленное (непустое) errorMessage и далее его не перезаписываем.
     * Если stopReason:"error" пришёл без errorMessage — используем fallback-сообщение.
     *
     * @param array<string, mixed> $decoded
     */
    private function applyErrorSignal(array $decoded): void
    {
        // Инвариант (приоритет первого осмысленного сообщения + fallback) живёт в VO;
        // парсер отвечает только за извлечение сигнала из pi-формата события.
        $this->errorState = $this->errorState->applyErrorSignal($this->extractErrorMessage($decoded));
    }

    /**
     * Извлекает errorMessage сигнала ошибки из события.
     *
     * Возвращает null, если stopReason не "error" (сигнала нет);
     * пустую строку, если stopReason "error", но без текста;
     * непустую строку с текстом ошибки модели.
     *
     * Для message_end/turn_end ошибка лежит во вложенном message, для agent_end —
     * в последнем assistant-сообщении массива messages.
     *
     * @param array<string, mixed> $decoded
     */
    private function extractErrorMessage(array $decoded): ?string
    {
        $type = $decoded['type'] ?? '';

        if ($type === 'agent_end') {
            return $this->extractErrorMessageFromAgentEnd($decoded);
        }

        $message = $decoded['message'] ?? null;
        if (is_array($message)) {
            return $this->extractErrorMessageFromMessage($message);
        }

        return null;
    }

    /**
     * Ищет errorMessage сигнала ошибки в последнем assistant-сообщении agent_end.
     *
     * @param array<string, mixed> $decoded
     */
    private function extractErrorMessageFromAgentEnd(array $decoded): ?string
    {
        $messages = $decoded['messages'] ?? [];
        if (!is_array($messages)) {
            return null;
        }

        for ($i = count($messages) - 1; $i >= 0; --$i) {
            $message = $messages[$i];
            if (!is_array($message) || ($message['role'] ?? '') !== 'assistant') {
                continue;
            }

            $errorMessage = $this->extractErrorMessageFromMessage($message);
            if ($errorMessage !== null) {
                return $errorMessage;
            }
        }

        return null;
    }

    /**
     * Извлекает errorMessage сигнала ошибки из одного сообщения.
     *
     * Допускает camelCase (stopReason/errorMessage) и snake_case (stop_reason/error_message)
     * варианты записи полей.
     *
     * @param array<string, mixed> $message
     */
    private function extractErrorMessageFromMessage(array $message): ?string
    {
        $stopReason = $message['stopReason'] ?? $message['stop_reason'] ?? null;
        if ($stopReason !== 'error') {
            return null;
        }

        $errorMessage = $message['errorMessage'] ?? $message['error_message'] ?? null;

        return is_string($errorMessage) ? $errorMessage : '';
    }

    /**
     * Извлекает usage-метрики из message_end.
     *
     * @param array<string, mixed> $decoded
     */
    private function applyUsageMetrics(array $decoded): void
    {
        $message = $decoded['message'] ?? [];
        if (!is_array($message)) {
            return;
        }

        $usage = $message['usage'] ?? [];
        if (!is_array($usage)) {
            return;
        }

        $cacheInfo = $usage['cache'] ?? [];
        if (!is_array($cacheInfo)) {
            $cacheInfo = [];
        }

        $costInfo = $usage['cost'] ?? [];
        if (!is_array($costInfo)) {
            $costInfo = [];
        }

        $this->inputTokens += (int) ($usage['input'] ?? 0);
        $this->outputTokens += (int) ($usage['output'] ?? 0);
        $this->turns = (int) ($usage['turns'] ?? 0);
        $this->cacheReadTokens += (int) ($cacheInfo['read'] ?? 0);
        $this->cacheWriteTokens += (int) ($cacheInfo['write'] ?? 0);
        $this->cost += (float) ($costInfo['total'] ?? 0.0);

        if (isset($message['model']) && is_string($message['model'])) {
            $this->model = $message['model'];
        }
    }

    /**
     * Извлекает текст последнего assistant-сообщения из agent_end.
     *
     * @param array<string, mixed> $decoded
     */
    private function extractLastAssistantText(array $decoded): string
    {
        $messages = $decoded['messages'] ?? [];
        if (!is_array($messages)) {
            return '';
        }

        for ($i = count($messages) - 1; $i >= 0; --$i) {
            $message = $messages[$i];
            if (!is_array($message)) {
                continue;
            }

            if (($message['role'] ?? '') !== 'assistant') {
                continue;
            }

            $text = $this->extractTextFromContent($message['content'] ?? []);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * @param mixed $content
     */
    private function extractTextFromContent(mixed $content): string
    {
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

    /**
     * Накапливает только text_delta fallback и не держит его в PHP-строке.
     *
     * @param array<string, mixed> $decoded
     */
    private function appendTextDeltaFallback(array $decoded): void
    {
        if ($this->hasAgentEnd) {
            return;
        }

        $delta = $this->extractTextDelta($decoded);
        if ($delta === '') {
            return;
        }

        fwrite($this->getTextDeltaFallbackStream(), $delta);
    }

    /**
     * Извлекает text_delta из message_update.
     *
     * @param array<string, mixed> $decoded
     */
    private function extractTextDelta(array $decoded): string
    {
        $event = $decoded['assistantMessageEvent'] ?? [];
        if (!is_array($event)) {
            return '';
        }

        if (($event['type'] ?? '') !== 'text_delta') {
            return '';
        }

        if (isset($event['delta']) && is_string($event['delta'])) {
            return $event['delta'];
        }

        return '';
    }

    /**
     * @return resource
     */
    private function getTextDeltaFallbackStream(): mixed
    {
        if (is_resource($this->textDeltaFallbackStream)) {
            return $this->textDeltaFallbackStream;
        }

        $stream = fopen(
            'php://temp/maxmemory:' . self::TEXT_DELTA_FALLBACK_MEMORY_LIMIT_BYTES,
            'w+',
        );
        if ($stream === false) {
            throw new RuntimeException('Unable to create pi text_delta fallback stream.');
        }

        $this->textDeltaFallbackStream = $stream;

        return $stream;
    }

    private function readTextDeltaFallback(): string
    {
        if (!is_resource($this->textDeltaFallbackStream)) {
            return '';
        }

        rewind($this->textDeltaFallbackStream);
        $text = stream_get_contents($this->textDeltaFallbackStream);
        if ($text === false) {
            return '';
        }

        return $text;
    }

    private function closeTextDeltaFallbackStream(): void
    {
        $stream = $this->textDeltaFallbackStream;
        $this->textDeltaFallbackStream = null;

        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}
