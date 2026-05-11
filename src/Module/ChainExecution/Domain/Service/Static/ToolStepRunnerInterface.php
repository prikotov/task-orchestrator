<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionToolStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ToolStepResultVo;

/**
 * Интерфейс выполнения tool-шага (детерминированной shell-команды).
 */
interface ToolStepRunnerInterface
{
    /**
     * Выполняет tool-шаг и возвращает результат.
     */
    public function run(ExecutionToolStepVo $tool): ToolStepResultVo;
}
