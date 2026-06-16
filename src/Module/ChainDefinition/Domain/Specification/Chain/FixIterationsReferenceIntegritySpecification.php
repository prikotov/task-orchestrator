<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;

/**
 * Спецификация ссылочной целостности групп fix-итераций цепочки.
 *
 * Формализует доменное бизнес-правило: каждое имя шага, на которое ссылается
 * группа fix-итераций, должно существовать среди именованных шагов цепочки
 * (ChainStepVo с непустым name), и одно имя шага не должно принадлежать
 * нескольким группам fix-итераций одновременно.
 *
 * Спецификация stateless, без I/O. Возвращает только bool — никогда не кидает
 * исключение и не формирует диагностических сообщений. Выброс исключения при
 * нарушении инварианта — ответственность внешней фабрики
 * ({@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory}),
 * а детальная диагностика для отчётов валидации — ответственность
 * {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainDefinitionValidatorService}.
 *
 * @see docs/conventions/layers/domain/specification.md
 */
final readonly class FixIterationsReferenceIntegritySpecification
{
    /**
     * Проверяет, что все ссылки групп fix-итераций корректны.
     *
     * @param list<ChainStepVo> $steps шаги цепочки
     * @param list<FixIterationGroupVo> $fixIterations группы fix-итераций
     *
     * @return bool true — если fixIterations пуст, либо каждое имя шага из групп
     *     существует среди именованных шагов и не принадлежит нескольким группам;
     *     false — если найдено неизвестное имя шага или имя, принадлежащее
     *     нескольким группам
     */
    public function isSatisfiedBy(array $steps, array $fixIterations): bool
    {
        if ($fixIterations === []) {
            return true;
        }

        $nameMap = [];
        foreach ($steps as $index => $step) {
            $stepName = $step->getName();
            if ($stepName !== null) {
                $nameMap[$stepName] = $index;
            }
        }

        $seenStepNames = [];
        foreach ($fixIterations as $group) {
            foreach ($group->getStepNames() as $stepName) {
                if (!isset($nameMap[$stepName])) {
                    return false;
                }

                if (isset($seenStepNames[$stepName])) {
                    return false;
                }

                $seenStepNames[$stepName] = true;
            }
        }

        return true;
    }
}
