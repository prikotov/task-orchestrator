<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\Release;

use Composer\InstalledVersions;
use TaskOrchestrator\Common\ValueObject\ReleaseVersionVo;

/**
 * Резолв версии релиза task-orchestrator (для параметра app.version и
 * изоляции кеш-корня ядра, см. Kernel::getCacheDir()).
 *
 * Кандидаты в порядке приоритета (семантика приоритетов и валидация SemVer —
 * в {@see ReleaseVersionVo::createFromCandidates()}):
 * 1. явный APP_RELEASE_VERSION (инъекция процесса сборки/окружения);
 * 2. pretty-версия пакета prikotov/task-orchestrator (Composer distribution);
 * 3. pretty-версия корневого пакета (root/PHAR).
 *
 * Stateless-хелпер точки сборки: читает суперглобаль $_SERVER и Composer
 * InstalledVersions при каждом вызове. Ядро создаёт его через `new` (вне
 * контейнера, на этапе сборки параметров) по образцу компонентов PharCache
 * (CacheRootOwner, CacheRootGarbageCollector).
 *
 * Вызовы Composer API статичны по дизайну библиотеки — sanctioned API
 * дистрибуции; подавления StaticAccess точечные, на методах чтения.
 */
final readonly class ReleaseVersionResolver
{
    /**
     * Резолвит действующую версию релиза из доступных кандидатов.
     */
    public function resolve(): string
    {
        return ReleaseVersionVo::createFromCandidates(
            explicitReleaseVersion: $this->readExplicitReleaseVersion(),
            packagePrettyVersion: $this->readPackagePrettyVersion(),
            rootPrettyVersion: $this->readRootPrettyVersion(),
        )->value();
    }

    /**
     * Явно переданная версия release tag (инъецируется процессом сборки/окружением).
     *
     * null — значение не передано. Чистый nullable-контракт для {@see ReleaseVersionVo};
     * валидацию SemVer выполняет Value Object.
     */
    private function readExplicitReleaseVersion(): ?string
    {
        $explicit = $_SERVER['APP_RELEASE_VERSION'] ?? null;

        return is_string($explicit) && $explicit !== '' ? $explicit : null;
    }

    /**
     * Точная pretty version (человекочитаемая версия) пакета Composer.
     *
     * Для Composer distribution (приложение как зависимость хост-проекта).
     * null, если Composer runtime недоступен или пакет не установлен.
     *
     * В отличие от нормализованного {@see InstalledVersions::getVersion()},
     * pretty-значение сохраняет исходную SemVer и не превращается в `1.0.0.0`.
     *
     * @SuppressWarnings("PHPMD.StaticAccess") Composer InstalledVersions —
     * официальный статический API Composer runtime.
     */
    private function readPackagePrettyVersion(): ?string
    {
        if (!class_exists(InstalledVersions::class)) {
            return null;
        }

        /** @var string|null $prettyVersion */
        $prettyVersion = InstalledVersions::getPrettyVersion('prikotov/task-orchestrator');

        return $prettyVersion;
    }

    /**
     * Точная pretty version корневого пакета.
     *
     * Для root/PHAR. null, если Composer runtime недоступен.
     *
     * @SuppressWarnings("PHPMD.StaticAccess") Composer InstalledVersions —
     * официальный статический API Composer runtime.
     */
    private function readRootPrettyVersion(): ?string
    {
        if (!class_exists(InstalledVersions::class)) {
            return null;
        }

        /** @var array<string, mixed> $rootPackage */
        $rootPackage = InstalledVersions::getRootPackage();
        $prettyVersion = $rootPackage['pretty_version'] ?? null;

        return is_string($prettyVersion) && $prettyVersion !== '' ? $prettyVersion : null;
    }
}
