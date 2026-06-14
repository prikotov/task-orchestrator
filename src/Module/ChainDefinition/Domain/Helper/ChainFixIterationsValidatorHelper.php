<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Helper;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;

/**
 * Валидатор ссылочной целостности групп fix-итераций цепочки.
 *
 * Проверяет, что имена шагов, на которые ссылаются группы fix-итераций,
 * существуют среди шагов цепочки и не принадлежат нескольким группам одновременно.
 * Чистая, не имеющая состояния утилита: детерминирована и не выполняет I/O.
 */
final class ChainFixIterationsValidatorHelper
{
    /**
     * Проверяет корректность ссылок групп fix-итераций на имена шагов цепочки.
     *
     * @param string $chainName имя цепочки (для сообщений об ошибках)
     * @param list<ChainStepVo> $steps шаги цепочки
     * @param list<FixIterationGroupVo> $fixIterations группы fix-итераций
     *
     * @throws InvalidArgumentException если группа ссылается на неизвестное имя шага
     *     или имя шага принадлежит нескольким группам одновременно
     */
    public static function assertValidReferences(string $chainName, array $steps, array $fixIterations): void
    {
        $nameMap = [];
        foreach ($steps as $index => $step) {
            $stepName = $step->getName();
            if ($stepName !== null) {
                $nameMap[$stepName] = $index;
            }
        }

        $allGroupStepNames = [];
        foreach ($fixIterations as $group) {
            foreach ($group->getStepNames() as $stepName) {
                if (!isset($nameMap[$stepName])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Chain "%s": fix iteration group "%s" references unknown step name "%s".',
                            $chainName,
                            $group->getGroup(),
                            $stepName,
                        ),
                    );
                }
                if (isset($allGroupStepNames[$stepName])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Chain "%s": step name "%s" belongs to multiple fix iteration groups ("%s" and "%s").',
                            $chainName,
                            $stepName,
                            $allGroupStepNames[$stepName],
                            $group->getGroup(),
                        ),
                    );
                }
                $allGroupStepNames[$stepName] = $group->getGroup();
            }
        }
    }
}
