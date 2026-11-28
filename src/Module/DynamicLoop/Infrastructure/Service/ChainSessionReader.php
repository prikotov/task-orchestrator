<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Infrastructure\Service;

/**
 * Чтение состояния сессии оркестрации.
 *
 * Stateless — все данные получает через параметры от ChainSessionLogger.
 */
final class ChainSessionReader
{
    /**
     * Возвращает относительные пути к response-файлам participant-раундов до шага $upToStep.
     *
     * Каждый элемент содержит роль участника и путь к файлу.
     *
     * @param array<int, array{system: string, user: string, role: string, is_facilitator: bool, round: int, duration: float, input_tokens: int, output_tokens: int, cost: float, model: string|null, provider: string|null, thinking: string|null, cache_read_tokens: int, cache_write_tokens: int, response?: string, error?: string, error_message?: string, invocation?: string}> $roundFiles
     *
     * @return list<array{role: string, path: string}>
     */
    public function getResponseFilePaths(
        ?string $currentSessionDir,
        string $basePath,
        array $roundFiles,
        int $upToStep,
    ): array {
        if ($currentSessionDir === null) {
            return [];
        }

        $paths = [];
        foreach ($roundFiles as $step => $data) {
            if ($step <= $upToStep && !$data['is_facilitator'] && isset($data['response'])) {
                $relative = substr($currentSessionDir, strlen($basePath) + 1)
                    . '/' . $data['response'];
                $paths[] = ['role' => $data['role'], 'path' => $relative];
            }
        }

        return $paths;
    }

    /**
     * Возвращает записи раундов в виде списка для Domain-контракта.
     *
     * Step-ключи — внутренняя деталь хранения; публичный контракт возвращает
     * упорядоченный список значений, поэтому здесь единственная точка
     * переиндексации 1..n → 0..n-1 (array_values).
     *
     * @param array<int, array{system: string, user: string, role: string, is_facilitator: bool, round: int, duration: float, input_tokens: int, output_tokens: int, cost: float, model: string|null, provider: string|null, thinking: string|null, cache_read_tokens: int, cache_write_tokens: int, response?: string, error?: string, error_message?: string, invocation?: string}> $roundFiles
     *
     * @return list<array{system: string, user: string, role: string, is_facilitator: bool, round: int, duration: float, input_tokens: int, output_tokens: int, cost: float, model: string|null, provider: string|null, thinking: string|null, cache_read_tokens: int, cache_write_tokens: int, response?: string, error?: string, error_message?: string, invocation?: string}>
     */
    public function getRoundFiles(array $roundFiles): array
    {
        return array_values($roundFiles);
    }
}
