<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\PharCache;

use InvalidArgumentException;

/**
 * Ограниченная очистка (сборка мусора) устаревших кеш-корней PHAR (контракт FR-4).
 *
 * Диспетчер перебирает узлы базового каталога `<sys_tmp>/task-orchestrator` и
 * удаляет ТОЛЬКО подтверждённо устаревшие корни. Узел считается устаревшим при
 * полном наборе признаков владения:
 *  1. это каталог, а не симлинк и не файл (проверка через lstat, без перехода
 *     внутрь по симлинку — traverse запрещён);
 *  2. `fileowner` каталога совпадает с `posix_getuid()` (POSIX; в Windows
 *     POSIX-функции недоступны — очистка не выполняется, ограничение
 *     задокументировано);
 *  3. маркер владения присутствует как обычный файл (не симлинк) и его
 *     self-hash совпадает с именем каталога; корни без маркера (legacy или
 *     незавершённые) сохраняются;
 *  4. повторный `flock(LOCK_EX|LOCK_NB)` на файл-маркер свободен — занятая
 *     блокировка означает активный корень чужого процесса.
 *
 * Текущий корень (совпадение имени с переданным self-hash) всегда исключён
 * из очистки (но учитывается в счётчике рассмотренных узлов). Симлинк-узел удаляется `unlink` самого узла (цель симлинка
 * не затрагивается); файлы и каталоги чужого владельца сохраняются. Очистка
 * выполняется только в PHAR-режиме — этот запрет контролирует вызывающая
 * сторона (Kernel): вне PHAR диспетчер не вызывается (no-op).
 *
 * Базовый каталог проверяется жёстко (гейт): узел должен быть каталогом
 * текущего пользователя (`fileowner` = `posix_getuid()`; чужой каталог не
 * трогается вообще — ни chmod, ни чтение содержимого), а безусловная попытка
 * `chmod 0700` — независимо от текущих прав, в том числе для каталога с
 * готовыми `0700` — подтверждает владение: сменить права может только
 * владелец узла. Невозможность установить права — задокументированное
 * безопасное поведение: очистка не выполняется, отчёт помечен причиной.
 * Идемпотентность: повторный запуск на тех же узлах не падает и не удаляет
 * лишнего (гонки обрабатываются через flock и lstat; исчезнувший между
 * scandir и lstat узел пропускается).
 *
 * Сбой удаления отдельного корня не прерывает перебор: факт фиксируется в
 * {@see CacheRootCleanupReport::$failures}; warning-семантику (не менять exit
 * code команды) обеспечивает Kernel. Диагностика не раскрывает содержимое кеша
 * ({@see CacheRootCleanupReport::toSafeSummary()} — только счётчики).
 *
 * Компонент без Symfony-зависимостей; намеренно НЕ регистрируется сервисом
 * контейнера и создаётся через `new` в Kernel на boot-этапе (до сборки
 * контейнера) — по образцу `HostPackageLayoutResolver` компонента Layout.
 *
 * Класс не final: protected-шов resolveOwnerUid() (по образцу resolvePharPath()
 * в Kernel) требует наследования в тестах — проверяемый шов, а не точка
 * расширения production-кода. Параметр $path (базовый каталог или проверяемый
 * узел) позволяет тестам имитировать раздельное владение (свой базовый
 * каталог при чужом корне).
 *
 * Оператор `@` на файловых операциях (scandir/lstat/unlink/rmdir/chmod) —
 * низкоуровневый I/O системного temp: подавляет только дублирующее
 * PHP-warning, результат каждой операции проверяется и трактуется как
 * безопасный отказ/сохранение узла (идиома для файловых функций PHP,
 * cf. bridge.php в phpmd.xml).
 *
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 */
class CacheRootGarbageCollector
{
    /** Права базового каталога, обязательные для выполнения очистки. */
    private const int BASE_DIR_MODE = 0o700;

    /**
     * Выполняет очистку устаревших кеш-корней.
     *
     * @param string $baseDir        базовый каталог `<sys_tmp>/task-orchestrator`
     * @param string $currentRootName self-hash текущего корня (исключается из очистки)
     */
    public function collect(string $baseDir, string $currentRootName): CacheRootCleanupReport
    {
        if ($currentRootName === '') {
            throw new InvalidArgumentException('Имя текущего кеш-корня не может быть пустым.');
        }

        $ownerUid = $this->resolveOwnerUid();

        if ($ownerUid === null) {
            return CacheRootCleanupReport::skipped(CacheRootCleanupReport::SKIP_REASON_POSIX_UNAVAILABLE);
        }

        if (!$this->secureBaseDirectory($baseDir)) {
            return CacheRootCleanupReport::skipped(CacheRootCleanupReport::SKIP_REASON_BASE_DIR_INSECURE);
        }

        $entries = @scandir($baseDir);

        if ($entries === false) {
            return CacheRootCleanupReport::skipped(CacheRootCleanupReport::SKIP_REASON_BASE_DIR_INSECURE);
        }

        $counters = new CacheRootCleanupCounters();

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $counters->examinedEntries++;
            $this->processEntry($baseDir, $entry, $currentRootName, $counters);
        }

        return $counters->toReport();
    }

    /**
     * Идентификатор владельца узла с точки зрения текущего процесса; null —
     * POSIX недоступен (Windows).
     *
     * В production владелец един для всех узлов — текущий POSIX-пользователь;
     * параметр $path (базовый каталог или проверяемый узел) существует только
     * для проверяемого шва: тесты имитируют раздельное владение (свой базовый
     * каталог при чужом корне).
     *
     * Protected — проверяемый шов для инъекции фиктивного владельца и имитации
     * отсутствия POSIX в тестах (по образцу resolvePharPath() в Kernel).
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter") $path — шов для тестов,
     * в production владелец един для всех узлов.
     */
    protected function resolveOwnerUid(?string $path = null): ?int
    {
        return function_exists('posix_getuid') ? posix_getuid() : null;
    }

    /**
     * Обрабатывает один узел базового каталога по lstat-политике диспетчера.
     */
    private function processEntry(
        string $baseDir,
        string $entry,
        string $currentRootName,
        CacheRootCleanupCounters $counters,
    ): void {
        if ($entry === $currentRootName) {
            return; // Текущий корень — всегда исключён из очистки.
        }

        $path = $baseDir . '/' . $entry;

        // lstat без перехода внутрь по симлинку: узел мог исчезнуть между
        // scandir и lstat (гонка параллельной очистки) — пропускаем.
        $stats = @lstat($path);

        if ($stats === false) {
            return;
        }

        if (is_link($path)) {
            // Симлинк удаляется через unlink самого узла; цель не затрагивается.
            if (@unlink($path)) {
                $counters->removedSymlinks++;

                return;
            }

            $counters->failures++;

            return;
        }

        if (!is_dir($path)) {
            // Файл или другой не-каталожный узел — не кеш-корень, сохраняется.
            $counters->unknownPreserved++;
            return;
        }

        $nodeOwnerUid = $this->resolveOwnerUid($path);

        if ($nodeOwnerUid === null || $stats['uid'] !== $nodeOwnerUid) {
            // Каталог чужого владельца — не наш корень, сохраняется.
            $counters->foreignPreserved++;
            return;
        }

        if (!$this->hasValidOwnershipMarker($path, $entry)) {
            // Корень без валидного маркера: legacy, незавершённый или повреждённый —
            // считается неизвестным и сохраняется.
            $counters->unknownPreserved++;
            return;
        }

        $lock = CacheRootOwnershipLock::acquireToInspect($path);

        if ($lock === null) {
            // Занятый flock — активный корень чужого процесса, сохраняется.
            $counters->activePreserved++;
            return;
        }

        // Устаревший корень подтверждён: маркер валиден, владелец наш, flock свободен.
        $removed = $this->removeTree($path);
        $lock->release();

        if (!$removed) {
            $counters->failures++;

            return;
        }

        $counters->removedRoots++;
    }

    /**
     * Проверяет маркер владения: обычный файл (не симлинк), содержимое
     * распознано и self-hash совпадает с именем каталога. Корни без маркера —
     * legacy или незавершённые — считаются неизвестными и сохраняются.
     */
    private function hasValidOwnershipMarker(string $rootDir, string $entry): bool
    {
        $markerPath = $rootDir . '/' . CacheRootOwnerMarker::FILENAME;

        if (is_link($markerPath) || !is_file($markerPath)) {
            return false;
        }

        $payload = @file_get_contents($markerPath);

        if ($payload === false) {
            return false;
        }

        return CacheRootOwnerMarker::decode($payload) === $entry;
    }

    /**
     * Гейт базового каталога: каталог текущего пользователя, защищаемый
     * правами `0700`; невозможность — задокументированный безопасный пропуск
     * очистки.
     *
     * Сначала сверяется владелец (шов resolveOwnerUid для базового каталога):
     * чужой узел (враждебный системный temp) не трогается вообще — ни chmod,
     * ни чтение содержимого. Затем выполняется безусловная попытка chmod —
     * независимо от текущих прав (каталог с готовыми `0700` тоже проверяется):
     * владение подтверждается самим chmod, сменить права может только владелец
     * узла, поэтому успешный chmod эквивалентен совпадению fileowner с
     * posix_getuid().
     */
    private function secureBaseDirectory(string $baseDir): bool
    {
        $stats = $this->lstatDirectory($baseDir);

        if ($stats === null) {
            return false;
        }

        $ownerUid = $this->resolveOwnerUid($baseDir);

        if ($ownerUid === null || $stats['uid'] !== $ownerUid) {
            // Чужой базовый каталог — не наш корень, не трогаем вообще.
            return false;
        }

        return @chmod($baseDir, self::BASE_DIR_MODE);
    }

    /**
     * lstat-факты каталога; null — узел отсутствует, является симлинком или
     * не каталогом (симлинк не разворачивается: traverse запрещён).
     *
     * @return array{uid: int, mode: int}|null
     */
    private function lstatDirectory(string $dir): ?array
    {
        if (is_link($dir)) {
            return null;
        }

        $stats = @lstat($dir);

        if ($stats === false || !is_dir($dir)) {
            return null;
        }

        return ['uid' => $stats['uid'], 'mode' => $stats['mode']];
    }

    /**
     * Рекурсивное удаление дерева без перехода по симлинкам: симлинк
     * отсоединяется `unlink`, каталоги обходятся, файлы удаляются.
     */
    private function removeTree(string $dir): bool
    {
        $entries = @scandir($dir);

        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;

            if (is_link($path)) {
                if (!@unlink($path)) {
                    return false;
                }

                continue;
            }

            if (is_dir($path)) {
                if (!$this->removeTree($path)) {
                    return false;
                }

                continue;
            }

            if (!@unlink($path)) {
                return false;
            }
        }

        return @rmdir($dir);
    }
}
