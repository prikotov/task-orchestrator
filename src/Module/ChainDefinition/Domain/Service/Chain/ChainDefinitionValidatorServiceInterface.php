<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainConfigViolationVo;

/**
 * Интерфейс Domain Service: валидация определения цепочки.
 */
interface ChainDefinitionValidatorServiceInterface
{
    /**
     * Валидирует определение цепочки и возвращает список нарушений.
     *
     * @return list<ChainConfigViolationVo>
     */
    public function validate(ChainDefinitionInterface $chain): array;
}
