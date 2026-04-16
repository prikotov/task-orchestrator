<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\ValueObject\QualityGateResultVo;
use TasK\Orchestrator\Domain\ValueObject\QualityGateVo;

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
