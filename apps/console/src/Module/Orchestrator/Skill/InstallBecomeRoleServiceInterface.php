<?php

declare(strict_types=1);

namespace TaskOrchestrator\Console\Module\Orchestrator\Skill;

/**
 * Специализированный Presentation-сервис установки общего skill `become-role`
 * в host-проект (команда `agent:init`).
 *
 * Обслуживает ровно один фиксированный ресурс `docs/agents/skills/become-role`
 * и НЕ является универсальным установщиком ресурсов: обобщение на другие skills
 * или каталоги требует отдельного архитектурного решения.
 *
 * Механизм выбирается по режиму дистрибуции:
 *  - source/Composer — относительный симлинк `<host>/.agents/skills/become-role`
 *    на каталог skill внутри пакета (без fallback на копирование);
 *  - PHAR — управляемая копия в том же пути плюс служебная runtime-привязка
 *    к физическому пути запущенного PHAR (симлинки на `phar://` недопустимы).
 *
 * Реализация: {@see \TaskOrchestrator\Console\Module\Orchestrator\Skill\Service\InstallBecomeRoleService}.
 */
interface InstallBecomeRoleServiceInterface
{
    /**
     * Установить skill become-role в host-проект.
     *
     * Идемпотентно: при уже актуальной установке возвращает успех без изменений.
     * Отличающийся целевой объект без $force — конфликт (отказ без изменений),
     * с $force — контролируемая замена только точного целевого пути.
     */
    public function install(bool $force): BecomeRoleInstallResultDto;
}
