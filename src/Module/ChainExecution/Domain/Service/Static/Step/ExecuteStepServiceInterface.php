<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StepContextVo;

/**
 * Интерфейс сервиса выполнения одного шага static-цепочки.
 *
 * Реализации: ExecuteAgentStepService, ExecuteQualityGateStepService, ExecuteToolStepService.
 * Резолвится через ResolveStepRunnerService по ChainStepTypeEnum.
 */
interface ExecuteStepServiceInterface
{
    /**
     * Определяет, поддерживает ли сервис данный тип шага.
     */
    public function supports(ChainStepTypeEnum $type): bool;

    /**
     * Выполняет шаг и возвращает результат.
     */
    public function run(ExecutionStepVo $step, StepContextVo $context): StaticStepResultVo;
}
