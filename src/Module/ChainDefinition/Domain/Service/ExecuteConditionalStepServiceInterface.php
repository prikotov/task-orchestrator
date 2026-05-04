<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionalStepResultVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;

/**
 * Integration Service: выполнение одного шага conditional-цепочки.
 *
 * Инкапсулирует вызов AI-агента или quality gate для одного шага.
 * Interface в Domain/Service/ (без подкаталога Integration/), реализация в Infrastructure.
 */
interface ExecuteConditionalStepServiceInterface
{
    /**
     * Выполняет один шаг conditional-цепочки (agent или quality gate).
     */
    public function executeStep(
        ChainStepVo $step,
        string $task,
        ?string $workingDir,
        int $timeout,
        ?string $previousContext,
        ?RoleConfigVo $roleConfig,
        bool $noContextFiles,
    ): ConditionalStepResultVo;
}
