<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\ValueObject;

/**
 * Версия релиза (Release version).
 *
 * Чистый (pure) механизм разрешения пользовательской версии приложения из
 * кандидатов разного происхождения. Не выполняет I/O и не читает глобальное
 * состояние — все источники версии передаются аргументами, поэтому логика
 * полностью детерминирована и покрывается unit-тестами.
 *
 * Порядок разрешения (приоритет сверху вниз):
 *  1. явно переданная версия release tag (explicitReleaseVersion) — например,
 *     инъецированная процессом сборки/окружением;
 *  2. точная pretty version (человекочитаемая версия) пакета Composer
 *     (packagePrettyVersion) — для Composer distribution, где приложение
 *     установлено как зависимость хост-проекта;
 *  3. точная pretty version корневого пакета (rootPrettyVersion) — для
 *     root/PHAR, где приложение является корневым пакетом.
 *
 * Из кандидата принимается только точная SemVer (Semantic Versioning 2.0.0):
 * `v?MAJOR.MINOR.PATCH` с допустимыми prerelease/build частями. Ведущий префикс
 * `v` удаляется без иных преобразований. Нормализованные Composer-значения
 * (`1.0.0.0`), ветки (`dev-main`) и маркер отсутствия версии
 * (`1.0.0+no-version-set`) НЕ принимаются как номер релиза.
 *
 * При отсутствии точной SemVer возвращается явный non-release marker (маркер
 * нерелизной сборки) {@see self::NON_RELEASE_MARKER} (`dev`): source checkout
 * (исходная рабочая копия) и нерелизная сборка никогда не маскируются под
 * опубликованный релиз.
 *
 * @see https://semver.org/spec/v2.0.0.html
 */
final readonly class ReleaseVersionVo
{
    /**
     * Маркер нерелизной сборки: возвращается, когда точная SemVer недоступна.
     */
    public const string NON_RELEASE_MARKER = 'dev';

    private function __construct(
        private string $value,
    ) {
    }

    /**
     * Разрешает версию приложения из кандидатов разного происхождения.
     *
     * Первый кандидат, являющийся точной SemVer (с допустимым удалением `v`),
     * побеждает. При отсутствии подходящего кандидата возвращается
     * {@see self::NON_RELEASE_MARKER}.
     *
     * @param string|null $explicitReleaseVersion явно переданная версия release tag
     * @param string|null $packagePrettyVersion   точная pretty version пакета Composer
     * @param string|null $rootPrettyVersion      точная pretty version корневого пакета
     */
    public static function createFromCandidates(
        ?string $explicitReleaseVersion,
        ?string $packagePrettyVersion,
        ?string $rootPrettyVersion,
    ): self {
        foreach ([$explicitReleaseVersion, $packagePrettyVersion, $rootPrettyVersion] as $candidate) {
            $normalized = self::createNormalizedCandidate($candidate);
            if ($normalized !== null) {
                return new self($normalized);
            }
        }

        return new self(self::NON_RELEASE_MARKER);
    }

    /**
     * Разрешённое значение версии для отображения пользователю.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Нормализует кандидат: точная SemVer с удалением ведущего префикса `v` либо null.
     *
     * Принимает `v?MAJOR.MINOR.PATCH` с допустимыми prerelease (`-...`) и build
     * (`+...`) частями по SemVer 2.0.0. Ведущий `v` удаляется без иных
     * преобразований. Нормализованные Composer-значения (4-компонентное
     * `1.0.0.0`, ветки `dev-*`, пустые строки) и маркер отсутствия версии
     * (`+no-version-set`) отклоняются.
     */
    private static function createNormalizedCandidate(?string $candidate): ?string
    {
        if ($candidate === null || $candidate === '') {
            return null;
        }

        $value = $candidate;
        if ($value[0] === 'v') {
            $value = substr($value, 1);
        }

        // Точная SemVer 2.0.0: MAJOR.MINOR.PATCH + необязательные prerelease/build.
        // Отклоняет нормализованные Composer-значения (4-компонентное `1.0.0.0`,
        // ветки `dev-*`) и прочий мусор.
        $identifier = '(?:0|[1-9]\d*|[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*)';
        $semverPattern = '/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)'
            . '(?:-' . $identifier . '(?:\.' . $identifier . ')*)?'
            . '(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/D';

        if (preg_match($semverPattern, $value) !== 1) {
            return null;
        }

        // Composer-маркер «нет версии» кодируется как build metadata
        // `+no-version-set`; такой кандидат не является релизной версией.
        if (str_ends_with($value, '+no-version-set')) {
            return null;
        }

        return $value;
    }
}
