<?php

declare(strict_types=1);

namespace TaskOrchestrator\Console\Module\Orchestrator\Skill\Service;

use Override;
use RuntimeException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use TaskOrchestrator\Console\Module\Orchestrator\Skill\BecomeRoleInstallOutcomeEnum;
use TaskOrchestrator\Console\Module\Orchestrator\Skill\BecomeRoleInstallResultDto;
use TaskOrchestrator\Console\Module\Orchestrator\Skill\InstallBecomeRoleServiceInterface;
use Throwable;

/**
 * Установка общего skill `become-role` в host-проект (Presentation-сервис).
 *
 * Специализированная реализация {@see InstallBecomeRoleServiceInterface} ровно
 * для одного ресурса — `docs/agents/skills/become-role` пакета. Универсальный
 * managed-copy установщик произвольных ресурсов — вне контракта этого сервиса.
 *
 * source/Composer-ветка (поведение сохранено): проверка источника, относительный
 * симлинк `<host>/.agents/skills/become-role` на skill пакета, идемпотентный
 * повтор, замена некорректного объекта через --force. Fallback симлинк → копия
 * отсутствует намеренно.
 *
 * PHAR-ветка: управляемая копия вместо симлинка на `phar://`. Полное ожидаемое
 * дерево (встроенный skill + одна runtime-привязка `.phar-binding` с физическим
 * путём PHAR) материализуется в уникальном соседнем staging-каталоге, проверяется
 * и только затем атомарно переключается на целевой путь через native rename.
 * Целевое дерево сравнивается побайтно (относительные пути + типы + sha256);
 * mtime и executable-bit в эквивалентность не входят — публичный запуск скрипта
 * выполняется через `bash`. Симлинки не разыменовываются ни в одной фазе.
 *
 * Гарантии ограничены: переносимой атомарной замены непустого каталога другим
 * каталогом не существует — между `target → backup` и `staging → target` остаётся
 * короткое окно; при перехваченной ошибке переключения выполняется откат, при
 * невозможности отката резервная копия сохраняется и её путь сообщается
 * пользователю. Блокировка конкурентного запуска находится в команде agent:init
 * и защищает только кооперативные параллельные запуски (не SIGKILL/отключение
 * питания/внешние процессы).
 *
 * Класс не объявлен final намеренно: protected {@see renamePath()} — проверяемый
 * тестовый шов коммит-фазы (инъекция отказа второго переименования для проверки
 * отката в integration-тестах).
 */
class InstallBecomeRoleService implements InstallBecomeRoleServiceInterface
{
    private const string SKILL_RELATIVE_PATH = 'docs/agents/skills/become-role';

    private const string TARGET_BASE_RELATIVE_PATH = '.agents/skills';

    private const string TARGET_NAME = 'become-role';

    private const string REQUIRED_SKILL_ENTRY = 'SKILL.md';

    private const string REQUIRED_SCRIPT_ENTRY = 'scripts/become-role.sh';

    /** Служебная runtime-привязка внутри управляемой копии: физический путь PHAR. */
    public const string PHAR_BINDING_FILENAME = '.phar-binding';

    private const string STAGING_PREFIX = '.become-role.staging.';

    private const string BACKUP_PREFIX = '.become-role.backup.';

    public function __construct(
        private readonly string $packageDir,
        private readonly string $basePath,
        private readonly bool $isPhar,
        private readonly ?string $pharPath,
        private readonly Filesystem $filesystem,
    ) {
    }

    #[Override]
    public function install(bool $force): BecomeRoleInstallResultDto
    {
        try {
            return $this->isPhar
                ? $this->installManagedCopy($force)
                : $this->installPackageSymlink($force);
        } catch (Throwable $error) {
            return new BecomeRoleInstallResultDto(
                BecomeRoleInstallOutcomeEnum::error,
                sprintf(
                    'Установка become-role не выполнена: %s Следующий шаг: проверьте права доступа к %s и повторите запуск.',
                    $error->getMessage(),
                    $this->targetDirPath(),
                ),
            );
        }
    }

    /**
     * source/Composer-ветка: относительный симлинк на skill пакета.
     */
    private function installPackageSymlink(bool $force): BecomeRoleInstallResultDto
    {
        $source = $this->sourcePath();
        $targetDir = $this->targetDirPath();
        $target = $this->targetPath();

        if (!is_dir($source) || !is_file($source . '/' . self::REQUIRED_SKILL_ENTRY)) {
            return new BecomeRoleInstallResultDto(
                BecomeRoleInstallOutcomeEnum::sourceMissing,
                sprintf('Skill become-role не найден в пакете: %s', $source),
            );
        }

        if ($this->isCorrectSymlink($target, $source)) {
            return new BecomeRoleInstallResultDto(
                BecomeRoleInstallOutcomeEnum::alreadyInstalled,
                sprintf('become-role уже установлен: %s -> %s', $target, $source),
            );
        }

        if ($this->targetExists($target) && !$force) {
            return new BecomeRoleInstallResultDto(
                BecomeRoleInstallOutcomeEnum::conflict,
                sprintf(
                    'Путь существует и не является корректным симлинком become-role: %s. Используйте --force для замены.',
                    $target,
                ),
            );
        }

        $this->filesystem->mkdir($targetDir);

        if ($this->targetExists($target)) {
            $this->filesystem->remove($target);
        }

        // Относительный путь от каталога симлинка к источнику — переносим при
        // перемещении проекта целиком.
        $relativeSource = Path::makeRelative($source, $targetDir);
        $this->filesystem->symlink($relativeSource, $target);

        return new BecomeRoleInstallResultDto(
            BecomeRoleInstallOutcomeEnum::installed,
            sprintf('become-role установлен: %s -> %s', $target, $source),
        );
    }

    /**
     * PHAR-ветка: управляемая копия встроенного skill + runtime-привязка.
     *
     * Последовательность: проверка родителей → проверка источника → материализация
     * staging → сверка staging с ожидаемым деревом → матрица целевого пути
     * (отсутствует / совпадает / отличается) → контролируемая замена с --force.
     */
    private function installManagedCopy(bool $force): BecomeRoleInstallResultDto
    {
        $pharPath = $this->pharPath;
        if ($pharPath === null || $pharPath === '') {
            throw new RuntimeException('физический путь запущенного PHAR недоступен; обновите PHAR-дистрибутив');
        }

        $parentGuard = $this->guardRealParents();
        if ($parentGuard !== null) {
            return $parentGuard;
        }

        $source = $this->sourcePath();
        if (
            !is_dir($source)
            || !is_file($source . '/' . self::REQUIRED_SKILL_ENTRY)
            || !is_file($source . '/' . self::REQUIRED_SCRIPT_ENTRY)
        ) {
            return new BecomeRoleInstallResultDto(
                BecomeRoleInstallOutcomeEnum::sourceMissing,
                sprintf(
                    'Встроенный skill become-role не найден или неполон в PHAR (обязательны %s и %s). Обновите PHAR-дистрибутив.',
                    self::REQUIRED_SKILL_ENTRY,
                    self::REQUIRED_SCRIPT_ENTRY,
                ),
            );
        }

        $sourceSnapshot = $this->snapshotTree($source);
        if ($sourceSnapshot === null) {
            return new BecomeRoleInstallResultDto(
                BecomeRoleInstallOutcomeEnum::sourceInvalid,
                'Встроенный skill become-role содержит недопустимый объект (симлинк или специальный файл). Обновите PHAR-дистрибутив.',
            );
        }

        $expectedSnapshot = $sourceSnapshot;
        $expectedSnapshot[self::PHAR_BINDING_FILENAME] = 'file:' . hash('sha256', $pharPath);
        ksort($expectedSnapshot);

        $targetDir = $this->targetDirPath();
        $target = $this->targetPath();
        $staging = $targetDir . '/' . self::STAGING_PREFIX . bin2hex(random_bytes(8));

        try {
            $this->filesystem->mkdir($staging);
            $this->copyTree($source, $staging);
            $this->filesystem->dumpFile($staging . '/' . self::PHAR_BINDING_FILENAME, $pharPath);

            // Повторная проверка подготовленного дерева: коммит выполняется
            // только для полного материализованного ожидаемого дерева.
            if ($this->snapshotTree($staging) !== $expectedSnapshot) {
                throw new IOException('подготовленная копия become-role не соответствует встроенному ресурсу');
            }

            if (!$this->targetExists($target)) {
                $this->commitRename($staging, $target);

                return new BecomeRoleInstallResultDto(
                    BecomeRoleInstallOutcomeEnum::installed,
                    sprintf('become-role установлен: %s (управляемая копия из PHAR)', $target),
                );
            }

            // target-симлинк никогда не разыменовывается: сам объект считается
            // отличающимся целевым состоянием (включая файл вместо каталога).
            $actualSnapshot = null;
            if (!is_link($target) && is_dir($target)) {
                $actualSnapshot = $this->snapshotTree($target);
            }

            if ($actualSnapshot !== null && $actualSnapshot === $expectedSnapshot) {
                return new BecomeRoleInstallResultDto(
                    BecomeRoleInstallOutcomeEnum::alreadyInstalled,
                    sprintf('become-role уже установлен: %s (копия актуальна)', $target),
                );
            }

            if (!$force) {
                return new BecomeRoleInstallResultDto(
                    BecomeRoleInstallOutcomeEnum::conflict,
                    sprintf(
                        'Путь %s существует и отличается от ожидаемой установки become-role из PHAR. Используйте --force для замены.',
                        $target,
                    ),
                );
            }

            return $this->replaceExistingTarget($staging, $target, $targetDir);
        } finally {
            // Штатная очистка временных артефактов: staging удаляется при любом
            // исходе; backup не трогается (управляется отдельно в replaceExistingTarget).
            if ($this->targetExists($staging)) {
                $this->filesystem->remove($staging);
            }
        }
    }

    /**
     * Замена существующего target на подготовленный staging (--force).
     *
     * target сначала переименовывается в соседний backup (без обхода его дерева,
     * включая случай target-симлинка), затем staging переключается на target.
     * При ошибке переключения выполняется откат backup → target; при невозможности
     * открата backup сохраняется и его путь сообщается пользователю — удалять
     * единственную сохранную копию ради формальной очистки нельзя.
     */
    private function replaceExistingTarget(string $staging, string $target, string $targetDir): BecomeRoleInstallResultDto
    {
        $backup = $targetDir . '/' . self::BACKUP_PREFIX . bin2hex(random_bytes(8));

        $this->commitRename($target, $backup);

        if (!$this->renamePath($staging, $target)) {
            if ($this->renamePath($backup, $target)) {
                throw new IOException(
                    'не удалось переключить подготовленный каталог; прежняя установка become-role восстановлена без изменений',
                );
            }

            throw new IOException(sprintf(
                'замена не выполнена и автоматическое восстановление невозможно; прежнее содержимое сохранено в резервной копии: %s. Восстановите каталог вручную и повторите agent:init --force',
                $backup,
            ));
        }

        $message = sprintf('become-role установлен: %s (управляемая копия из PHAR)', $target);

        // Backup удаляется только после успешного переключения; неудачное
        // удаление не отменяет установку.
        try {
            $this->filesystem->remove($backup);
        } catch (IOException) {
            $message .= sprintf(' Резервная копия не удалена автоматически: %s.', $backup);
        }

        return new BecomeRoleInstallResultDto(BecomeRoleInstallOutcomeEnum::installed, $message);
    }

    /**
     * Родительские каталоги .agents и .agents/skills: только обычные каталоги.
     *
     * lstat-семантика: симлинк или объект другого типа — отказ до любых записей,
     * чтобы установка не следовала по чужим симлинкам вглубь host-структуры.
     */
    private function guardRealParents(): ?BecomeRoleInstallResultDto
    {
        $agentsDir = Path::canonicalize($this->basePath . '/' . dirname(self::TARGET_BASE_RELATIVE_PATH));

        foreach ([$agentsDir, $this->targetDirPath()] as $dir) {
            if (is_link($dir)) {
                return new BecomeRoleInstallResultDto(
                    BecomeRoleInstallOutcomeEnum::error,
                    sprintf(
                        '.agents-структура host-проекта недопустима: %s является симлинком. Замените его обычным каталогом и повторите agent:init.',
                        $dir,
                    ),
                );
            }

            if (file_exists($dir) && !is_dir($dir)) {
                return new BecomeRoleInstallResultDto(
                    BecomeRoleInstallOutcomeEnum::error,
                    sprintf(
                        '.agents-структура host-проекта недопустима: %s не является каталогом. Исправьте структуру и повторите agent:init.',
                        $dir,
                    ),
                );
            }
        }

        return null;
    }

    private function isCorrectSymlink(string $target, string $expectedSource): bool
    {
        if (!is_link($target)) {
            return false;
        }

        $linkTarget = readlink($target);
        if ($linkTarget === false) {
            return false;
        }

        $resolved = Path::canonicalize(dirname($target) . '/' . $linkTarget);

        return $resolved === Path::canonicalize($expectedSource);
    }

    /**
     * Существование целевого объекта без следования по симлинку.
     */
    private function targetExists(string $path): bool
    {
        return is_link($path) || file_exists($path);
    }

    /**
     * Снимок дерева каталога для сравнения установок.
     *
     * @return array<string, string>|null отсортированное отображение «относительный
     *                                    путь => тип»: 'dir' или 'file:<sha256>';
     *                                    null, если дерево содержит симлинк или
     *                                    специальный файл (либо нечитаемо)
     */
    private function snapshotTree(string $root): ?array
    {
        $snapshot = [];

        if (!$this->collectSnapshot($root, '', $snapshot)) {
            return null;
        }

        ksort($snapshot);

        return $snapshot;
    }

    /**
     * @param array<string, string> $snapshot
     */
    private function collectSnapshot(string $dir, string $prefix, array &$snapshot): bool
    {
        $entries = scandir($dir);
        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            $relative = $prefix === '' ? $entry : $prefix . '/' . $entry;

            if (is_link($path)) {
                return false;
            }

            if (is_dir($path)) {
                $snapshot[$relative] = 'dir';

                if (!$this->collectSnapshot($path, $relative, $snapshot)) {
                    return false;
                }

                continue;
            }

            if (is_file($path)) {
                $hash = hash_file('sha256', $path);
                if ($hash === false) {
                    return false;
                }

                $snapshot[$relative] = 'file:' . $hash;

                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Копия дерева источник → назначение без следования по симлинкам.
     *
     * Симлинки и специальные файлы в источнике запрещены: копирование встроенного
     * ресурса не может неявно выйти за его пределы. Диагностика ошибки чтения
     * оперирует только путём относительно корня пакета (SKILL_RELATIVE_PATH):
     * абсолютный источник, включая схему phar://, — внутренняя деталь реализации
     * и в пользовательские сообщения не попадает.
     */
    private function copyTree(string $source, string $destination, string $relative = ''): void
    {
        // Отказ чтения обрабатывается через false; warning подавляется тем же
        // приёмом, что и в renamePath/chmod — диагностику формирует сам сервис.
        $entries = @scandir($source);
        if ($entries === false) {
            throw new IOException(sprintf(
                'не удалось прочитать каталог встроенного skill become-role в PHAR: %s. Обновите PHAR-дистрибутив.',
                $relative === '' ? self::SKILL_RELATIVE_PATH : self::SKILL_RELATIVE_PATH . '/' . $relative,
            ));
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $from = $source . '/' . $entry;
            $to = $destination . '/' . $entry;

            if (is_link($from)) {
                throw new IOException(sprintf('симлинк в источнике become-role: %s', $entry));
            }

            if (is_dir($from)) {
                $this->filesystem->mkdir($to);
                $this->copyTree($from, $to, $relative === '' ? $entry : $relative . '/' . $entry);

                continue;
            }

            if (is_file($from)) {
                $this->filesystem->copy($from, $to);

                // Записи PHAR читаются как 0444, а Symfony Filesystem::copy сохраняет
                // режим источника (как cp): без нормализации управляемая копия стала
                // бы read-only и host-проект не мог изменить файлы. Права не входят в
                // контракт эквивалентности, поэтому нормализуем в стандартные 0644
                // (запуск публичного контракта выполняется через bash, без exec-bit).
                if (!@chmod($to, 0644)) {
                    throw new IOException(sprintf('не удалось назначить права файла %s', $to));
                }

                continue;
            }

            throw new IOException(sprintf('специальный файл в источнике become-role: %s', $entry));
        }
    }

    /**
     * Единственная точка коммита: атомарный native rename соседних путей.
     *
     * Symfony Filesystem::rename не используется намеренно: при неудаче native
     * rename каталога он переходит на mirror()+remove() — частичное рекурсивное
     * копирование, недопустимое на фазе переключения target.
     */
    private function commitRename(string $from, string $to): void
    {
        if (!$this->renamePath($from, $to)) {
            throw new IOException(sprintf('не удалось выполнить переименование %s в %s', $from, $to));
        }
    }

    /**
     * Проверяемый native rename без copy-fallback.
     *
     * Защищённый метод — тестовый шов для управляемой инъекции отказа на фазе
     * отката (воспроизведение ошибки второго переименования в тестах).
     */
    protected function renamePath(string $from, string $to): bool
    {
        return @rename($from, $to);
    }

    private function sourcePath(): string
    {
        return Path::canonicalize($this->packageDir . '/' . self::SKILL_RELATIVE_PATH);
    }

    private function targetDirPath(): string
    {
        return Path::canonicalize($this->basePath . '/' . self::TARGET_BASE_RELATIVE_PATH);
    }

    private function targetPath(): string
    {
        return Path::canonicalize($this->targetDirPath() . '/' . self::TARGET_NAME);
    }
}
