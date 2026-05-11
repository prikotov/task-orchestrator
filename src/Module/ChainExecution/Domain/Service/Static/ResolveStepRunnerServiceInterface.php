<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;

/**
 * Интерфейс фабрики: резолвит ExecuteStepServiceInterface по ChainStepTypeEnum.
 */
interface ResolveStepRunnerServiceInterface
{
    /**
     * Возвращает сервис выполнения для заданного типа шага.
     *
     * @throws \LogicException если подходящий сервис не найден
     */
    public function resolve(ChainStepTypeEnum $type): ExecuteStepServiceInterface;
}
