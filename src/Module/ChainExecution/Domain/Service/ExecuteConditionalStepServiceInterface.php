<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ConditionalStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRoleConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;

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
        ExecutionStepVo $step,
        string $task,
        ?string $workingDir,
        int $timeout,
        ?string $previousContext,
        ?ExecutionRoleConfigVo $roleConfig,
        bool $noContextFiles,
    ): ConditionalStepResultVo;
}
