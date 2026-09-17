<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Service;

use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\AgentRoleException;

/**
 * Резолвит аргумент «имя роли | путь к файлу роли» в файл роли.
 *
 * Инфраструктурный контракт: реализация различает имя роли (snake_case, без
 * «/» и суффикса `.md`) и путь к файлу роли. Имя резолвится локатором
 * {@see LocateRoleFileServiceInterface} (каталог ролей + локаль). Путь
 * проверяется по базисам: абсолютный — как есть, относительный — от каждого
 * базиса с лексическим сворачиванием «..» (семантика `cd -L` bash: без
 * физического раскрытия симлинк-компонентов базиса).
 *
 * Назначение: агент может передать путь, отсчитанный от произвольного cwd или
 * от логического PWD шелла (например, после `cd` в каталог skill-а по
 * симлинку); резолвинг принадлежит домену, а не glue-скрипту become-role.
 */
interface ResolveRoleArgumentServiceInterface
{
    /**
     * @param string $argument имя роли (snake_case) или путь к файлу роли
     * @param list<string> $bases базисы для относительных путей (абсолютные каталоги;
     *                             первый найденный файл выигрывает)
     *
     * @return string абсолютный путь к существующему файлу роли
     *
     * @throws AgentRoleException файл не найден ни по одному базису
     *                             ({@see \TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\RoleArgumentNotFoundException})
     *                             либо роль по имени не найдена
     */
    public function resolve(string $argument, array $bases): string;
}
