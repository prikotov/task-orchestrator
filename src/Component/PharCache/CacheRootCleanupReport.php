<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\PharCache;

/**
 * Итог одного прохода очистки кеш-корней PHAR (результат {@see CacheRootGarbageCollector::collect()}).
 *
 * ДТО со счётчиками без путей: безопасная сводка {@see toSafeSummary()} намеренно
 * не раскрывает ни имена корней, ни содержимое кеша — warning-семантика FR-4
 * запрещает утечку деталей кеша в диагностику.
 */
final readonly class CacheRootCleanupReport
{
    /** Очистка пропущена: POSIX-функции недоступны (Windows). */
    public const string SKIP_REASON_POSIX_UNAVAILABLE = 'posix-unavailable';

    /** Очистка пропущена: базовый каталог отсутствует, не защищён или чужой. */
    public const string SKIP_REASON_BASE_DIR_INSECURE = 'base-dir-insecure';

    /**
     * @param bool        $performed       очистка выполнялась (гейт базового каталога пройден)
     * @param string|null $skipReason      причина пропуска (константы SKIP_REASON_*), null — выполнялась
     * @param int         $examinedEntries узлов рассмотрено (без `.`/`..`; текущий корень учитывается как рассмотренный)
     * @param int         $removedRoots    устаревших кеш-корней удалено
     * @param int         $removedSymlinks симлинк-узлов удалено (unlink самого узла)
     * @param int         $activePreserved активных корней сохранено (занятый flock)
     * @param int         $unknownPreserved неизвестных узлов сохранено (без валидного маркера: legacy, незавершённые, файлы)
     * @param int         $foreignPreserved чужих каталогов сохранено (владелец не совпал)
     * @param int         $failures        сбоев удаления (не меняют exit code команды)
     */
    public function __construct(
        public readonly bool $performed,
        public readonly ?string $skipReason = null,
        public readonly int $examinedEntries = 0,
        public readonly int $removedRoots = 0,
        public readonly int $removedSymlinks = 0,
        public readonly int $activePreserved = 0,
        public readonly int $unknownPreserved = 0,
        public readonly int $foreignPreserved = 0,
        public readonly int $failures = 0,
    ) {}

    /**
     * Отчёт о пропущенной очистке (гейт не пройден: нет POSIX или базовый каталог не защищён).
     *
     * @param string $skipReason константа SKIP_REASON_* компонента
     */
    public static function skipped(string $skipReason): self
    {
        return new self(performed: false, skipReason: $skipReason);
    }

    /**
     * Была ли зафиксирована хотя бы одна ошибка удаления.
     */
    public function hasFailures(): bool
    {
        return $this->failures > 0;
    }

    /**
     * Безопасная сводка для диагностики: только счётчики, без путей и содержимого кеша.
     */
    public function toSafeSummary(): string
    {
        $preserved = $this->activePreserved + $this->unknownPreserved + $this->foreignPreserved;

        return sprintf(
            'рассмотрено: %d, удалено: %d, сохранено: %d, ошибок: %d',
            $this->examinedEntries,
            $this->removedRoots + $this->removedSymlinks,
            $preserved,
            $this->failures,
        );
    }
}
