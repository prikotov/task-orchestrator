<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\PharCache;

use InvalidArgumentException;

/**
 * Захват кеш-корня PHAR во владение: `mkdir` → `flock` → маркер (контракт FR-4).
 *
 * Порядок создания корня зафиксирован контрактом и не подлежит изменению:
 *  1. `mkdir` базового каталога `<sys_tmp>/task-orchestrator` и корня
 *     `<hash>` с правами `0700` (после `mkdir` права подтверждаются `chmod`,
 *     так как режим `mkdir` искажается umask);
 *  2. эксклюзивный `flock(LOCK_EX|LOCK_NB)` на файл-маркер — занятая блокировка
 *     означает активный чужой корень (параллельный запуск);
 *  3. запись маркера владения со self-hash — до записи маркера корень считается
 *     незавершённым и очисткой не удаляется.
 *
 * На POSIX владение подтверждается только при совпадении `fileowner` базового
 * каталога и корня с `posix_getuid()`: чужой по владельцу узел (враждебный
 * системный temp) не занимается и не трогается вообще — владелец проверяется
 * до любых изменений узла (включая chmod). В Windows POSIX-функции недоступны
 * — проверка владельца пропускается (ограничение задокументировано), сама
 * изоляция и блокировка работают.
 *
 * Метод {@see claim()} не бросает исключений при невозможности захвата:
 * возвращается null, загрузка ядра продолжается (root-причина — активный чужой
 * корень, незащищённые права или враждебный узел). Это безопасное поведение,
 * задокументированное в контракте: невозможность владения не должна ломать
 * основную команду.
 *
 * Компонент без Symfony-зависимостей (только файловые операции); намеренно НЕ
 * регистрируется сервисом контейнера и создаётся через `new` в Kernel на
 * boot-этапе (до сборки контейнера) — по образцу чистого резолвера
 * `HostPackageLayoutResolver` компонента Layout.
 *
 * Класс не final: protected-шов resolveOwnerUid() (по образцу resolvePharPath()
 * в Kernel) требует наследования в тестах — проверяемый шов, а не точка
 * расширения production-кода.
 *
 * Оператор `@` на файловых операциях (mkdir/chmod/lstat) — низкоуровневый
 * I/O системного temp: подавляет только дублирующее PHP-warning, результат
 * каждой операции проверяется и трактуется как безопасный отказ захвата
 * (идиома для файловых функций PHP, cf. bridge.php в phpmd.xml).
 *
 * @SuppressWarnings("PHPMD.ErrorControlOperator")
 */
class CacheRootOwner
{
    /** Права базового каталога и кеш-корня. */
    private const int DIRECTORY_MODE = 0o700;

    /**
     * Захватывает корень во владение.
     *
     * @param string $baseDir  базовый каталог `<sys_tmp>/task-orchestrator`
     * @param string $rootName self-hash корня (= имя каталога корня)
     */
    public function claim(string $baseDir, string $rootName): ?CacheRootOwnershipLock
    {
        if ($rootName === '') {
            throw new InvalidArgumentException('Имя кеш-корня не может быть пустым.');
        }

        if (!$this->ensureOwnedDirectory($baseDir)) {
            return null;
        }

        if (!$this->ensureOwnedDirectory($baseDir . '/' . $rootName)) {
            return null;
        }

        $lock = CacheRootOwnershipLock::acquireToOwn($baseDir . '/' . $rootName);

        if ($lock === null) {
            // Активный чужой корень: маркер не пишем (порядок контракта — flock → маркер).
            return null;
        }

        if (!$lock->writeMarker($rootName)) {
            $lock->release();

            return null;
        }

        return $lock;
    }

    /**
     * Идентификатор текущего POSIX-пользователя; null — POSIX недоступен (Windows).
     *
     * Protected — проверяемый шов для инъекции фиктивного владельца в тестах
     * (по образцу resolvePharPath() в Kernel).
     */
    protected function resolveOwnerUid(): ?int
    {
        return function_exists('posix_getuid') ? posix_getuid() : null;
    }

    /**
     * Обеспечивает существование каталога текущего пользователя с правами
     * `0700` (без перехода внутрь по симлинку: симлинк-узел не создаётся
     * и не расширяется).
     *
     * Владелец существующего узла проверяется ДО любых изменений (включая
     * chmod): враждебный/чужой узел не трогается вообще. Для отсутствующего
     * узла выполняется `mkdir`, после чего владение подтверждается той же
     * проверкой (защита от гонки, когда узел создан параллельным процессом).
     */
    private function ensureOwnedDirectory(string $dir): bool
    {
        if (is_link($dir)) {
            return false;
        }

        if (!is_dir($dir) && !@mkdir($dir, self::DIRECTORY_MODE, true) && !is_dir($dir)) {
            return false;
        }

        if (!$this->isOwnedByCurrentUser($dir)) {
            // Враждебный/чужой узел: отказ без chmod — не трогаем вообще.
            return false;
        }

        // Права mkdir искажаются umask — подтверждаем chmod явно (только свой узел).
        return @chmod($dir, self::DIRECTORY_MODE);
    }

    /**
     * Подтверждает владение каталога текущим POSIX-пользователем через lstat
     * (без перехода по симлинку). В Windows (нет POSIX) проверка пропускается.
     */
    private function isOwnedByCurrentUser(string $dir): bool
    {
        $ownerUid = $this->resolveOwnerUid();

        if ($ownerUid === null) {
            return true;
        }

        $stats = $this->lstatDirectory($dir);

        return $stats !== null && $stats['uid'] === $ownerUid;
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
}
