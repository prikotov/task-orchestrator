<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionQualityGateVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\QualityGateResultVo;

/**
 * Интерфейс выполнения quality gate.
 */
interface QualityGateRunnerInterface
{
    /**
     * Выполняет quality gate и возвращает результат.
     */
    public function run(ExecutionQualityGateVo $gate): QualityGateResultVo;
}
