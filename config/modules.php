<?php

declare(strict_types=1);

/**
 * Реестр модулей TaskOrchestrator.
 *
 * Формат записи соответствует конвенции docs/conventions/symfony-folder-structure.md
 * и TasK-референсу: класс модуля => массив окружений, в которых модуль активен.
 *
 * Ключ 'all' => true означает «модуль включён во всех окружениях». Проект
 * task-orchestrator — CLI-бандл без Symfony Kernel, поэтому реестр содержит
 * единое окружение; другие env-ключи зарезервированы для будущей совместимости
 * с конвенцией.
 *
 * TaskOrchestratorExtension::load() перебирает этот реестр и для каждого
 * модуля, реализующего ModuleInterface, регистрирует ModuleCompilerPass,
 * подгружающий Resource/config/services.yaml модуля.
 *
 * @return array<string, array<string, bool>>
 */
return [
    TaskOrchestrator\Common\Module\GitIdentity\GitIdentityModule::class => ['all' => true],
];
