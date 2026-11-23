<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Helper;

use LogicException;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRoleConfigVo;

/**
 * Helper разбора CLI-команды роли dynamic-цикла (секция command в конфигурации роли).
 *
 * Вынесено из
 * {@see \TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\ExecuteDynamicTurnService}:
 * исполнителю turn'ов остаётся оркестрация шагов, а технический разбор команды роли — здесь.
 *
 * Helper по docs/conventions/core-patterns/helper.md: только статические pure-функции —
 * без состояния, DI и I/O. Static-контракт санкционирован ignorepattern StaticAccess
 * в phpmd.xml (parse* — чистый разбор без побочных эффектов).
 */
final class DynamicRoleCommandHelper
{
    /**
     * Имя запускателя — первый непустой элемент команды роли.
     *
     * @param DynamicLoopRoleConfigVo|null $roleConfig конфигурация роли или null
     *                                                 (роль не переопределена в конфиге)
     *
     * @throws LogicException если команда роли отсутствует или первый элемент пуст
     */
    public static function parseRunner(?DynamicLoopRoleConfigVo $roleConfig): string
    {
        $command = $roleConfig?->getCommand() ?? [];
        if ($command === [] || $command[0] === '') {
            throw new LogicException(
                'Role configuration must define a non-empty command with runner name as the first element.',
            );
        }

        return $command[0];
    }

    /**
     * Извлекает уровень thinking из команды роли (профиль command: `--thinking <level>`).
     *
     * @param DynamicLoopRoleConfigVo|null $roleConfig конфигурация роли или null
     *                                                 (роль не переопределена в конфиге)
     *
     * @return string|null уровень thinking; null — флаг отсутствует, без значения
     *                     или значение выглядит как следующий флаг
     */
    public static function parseThinking(?DynamicLoopRoleConfigVo $roleConfig): ?string
    {
        $command = $roleConfig?->getCommand() ?? [];
        foreach ($command as $i => $part) {
            if ($part === '--thinking' && isset($command[$i + 1])) {
                $next = $command[$i + 1];

                return str_starts_with($next, '--') ? null : $next;
            }
        }

        return null;
    }
}
