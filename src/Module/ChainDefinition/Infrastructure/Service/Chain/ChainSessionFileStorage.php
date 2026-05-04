<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain;

use RuntimeException;

/**
 * Абстракция файловых операций для сессии оркестрации.
 *
 * Инкапсулирует создание директорий, чтение/запись файлов,
 * разрешение путей и построение имён файлов шагов.
 */
final class ChainSessionFileStorage
{
    /**
     * Создаёт директорию рекурсивно.
     */
    public function createDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $path));
        }
    }

    /**
     * Записывает содержимое в файл.
     */
    public function writeFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(sprintf('Failed to write file: %s', $path));
        }
    }

    /**
     * Читает содержимое файла.
     */
    public function readFile(string $path): string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read file: %s', $path));
        }

        return $content;
    }

    /**
     * Разрешает путь относительно sessionDir.
     *
     * Если путь уже абсолютный — возвращает как есть.
     * Если относительный — склеивает с $sessionDir.
     */
    public function resolvePath(string $sessionDir, string $path): string
    {
        if ($path === '') {
            return '';
        }

        return str_starts_with($path, '/') ? $path : $sessionDir . '/' . $path;
    }

    /**
     * Строит базовое имя файла для шага/раунда/роли.
     *
     * Пример: step_001_round_001_system_architect_loki
     */
    public function buildStepBaseName(int $step, int $round, string $role): string
    {
        return sprintf(
            'step_%s_round_%s_%s',
            str_pad((string) $step, 3, '0', STR_PAD_LEFT),
            str_pad((string) $round, 3, '0', STR_PAD_LEFT),
            $role,
        );
    }
}
