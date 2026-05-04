<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum;

/**
 * Тип цепочки оркестрации (ChainExecution-собственный).
 *
 * Введён для устранения зависимости ChainExecution.Application от ChainDefinition.Domain.
 * Маппится из ChainTypeEnum в Integration-слое (ChainExecutionDefinitionMapper).
 */
enum ChainExecutionTypeEnum: string
{
    case staticType = 'static';
    case dynamicType = 'dynamic';
    case conditionalType = 'conditional';
}
