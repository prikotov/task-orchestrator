<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainConfigViolationVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;

/**
 * Domain Service: единый источник detailed-диагностики нарушений ссылочной
 * целостности fix-итераций.
 *
 * Переиспользуется двумя потребителями, что гарантирует отсутствие второго источника
 * detailed-сообщений:
 *  - валидатором {@see ChainDefinitionValidatorServiceInterface} (делегирует inline-цикл);
 *  - validate-путём Application-слоя по raw-данным из
 *    {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\InvalidFixIterationsException}.
 */
interface CollectFixIterationsViolationsServiceInterface
{
    /**
     * Собирает detailed-нарушения ссылочной целостности fix-итераций.
     *
     * Типы нарушений: неизвестное имя шага (unknown step); имя шага, принадлежащее
     * нескольким группам (multiple groups). Порядок и тексты сообщений синхронизированы
     * с {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification}.
     *
     * @param string $chainName имя цепочки
     * @param list<ChainStepVo> $steps шаги цепочки
     * @param list<FixIterationGroupVo> $fixIterations группы fix-итераций
     *
     * @return list<ChainConfigViolationVo> пустой список — нарушений нет
     */
    public function collect(string $chainName, array $steps, array $fixIterations): array;
}
