<?php

declare(strict_types=1);

namespace TaskOrchestrator\Console\Module\Orchestrator\Skill;

/**
 * Исход установки skill `become-role` в host-проект.
 *
 * Отделяет идемпотентный успех и ожидаемые отказы (конфликт, неполный источник)
 * от непредвиденных ошибок, чтобы команда `agent:init`
 * ({@see \TaskOrchestrator\Console\Module\Orchestrator\Command\InitCommand})
 * выбирала способ отображения и код завершения без знания файловой механики.
 * Какие исходы считать успешными, решает команда (транспортная граница).
 */
enum BecomeRoleInstallOutcomeEnum: string
{
    /** Установка выполнена: создан симлинк (source/Composer) или управляемая копия (PHAR). */
    case installed = 'installed';

    /** Ожидаемая установка уже на месте; изменения не требуются и не производились. */
    case alreadyInstalled = 'already_installed';

    /** Целевой путь занят отличающимся объектом; замена возможна только с --force. */
    case conflict = 'conflict';

    /** Встроенный ресурс become-role отсутствует или неполон. */
    case sourceMissing = 'source_missing';

    /** Встроенный ресурс become-role содержит недопустимые объекты (симлинк, специальный файл). */
    case sourceInvalid = 'source_invalid';

    /** Непредвиденная ошибка (доступ к файловой системе, неудачное переключение каталогов). */
    case error = 'error';
}
