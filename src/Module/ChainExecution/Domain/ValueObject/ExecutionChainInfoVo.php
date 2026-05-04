<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainExecutionTypeEnum;

/**
 * Лёгкий VO с идентификацией цепочки (ChainExecution-собственный).
 *
 * Заменяет ChainDefinitionInterface в ExecutionStrategyInterface,
 * устраняя зависимость Application-слоя от ChainDefinition.Domain.
 *
 * Содержит только имя и тип цепочки — достаточно для маршрутизации стратегии.
 * Полная конфигурация загружается стратегией отдельно по имени.
 */
final readonly class ExecutionChainInfoVo
{
    public function __construct(
        public string $name,
        public ChainExecutionTypeEnum $type,
    ) {}
}
