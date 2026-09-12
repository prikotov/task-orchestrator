<?php

declare(strict_types=1);

namespace TaskOrchestrator\Console\Module\Orchestrator\Skill;

/**
 * Результат установки skill `become-role` в host-проект.
 *
 * Неизменяемый транспорт между специализированным Presentation-сервисом
 * установки и консольной командой `agent:init`
 * ({@see \TaskOrchestrator\Console\Module\Orchestrator\Command\InitCommand}):
 * исход + готовое человекочитаемое сообщение. Команда только отображает
 * результат и мапит исход в код завершения.
 */
final readonly class BecomeRoleInstallResultDto
{
    public function __construct(
        public readonly BecomeRoleInstallOutcomeEnum $outcome,
        public readonly string $message,
    ) {
    }
}
