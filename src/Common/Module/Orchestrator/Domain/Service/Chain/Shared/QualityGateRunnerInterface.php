<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\QualityGateResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\QualityGateVo;

/**
 * Интерфейс выполнения quality gate.
 *
 * Реализация выполняет shell-команду через Symfony Process
 * и возвращает результат выполнения.
 */
interface QualityGateRunnerInterface
{
    /**
     * Выполняет quality gate и возвращает результат.
     */
    public function run(QualityGateVo $gate): QualityGateResultVo;
}
