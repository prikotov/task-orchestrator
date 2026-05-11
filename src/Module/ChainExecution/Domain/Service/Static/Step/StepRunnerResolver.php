<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;

/**
 * Фабрика: резолвит StepRunnerInterface по ChainStepTypeEnum.
 *
 * Принимает iterable<StepRunnerInterface> (tagged services),
 * находит подходящий runner по supports().
 */
final readonly class StepRunnerResolver
{
    /**
     * @param iterable<StepRunnerInterface> $runners
     */
    public function __construct(
        private iterable $runners,
    ) {
    }

    /**
     * Возвращает стратегию выполнения для заданного типа шага.
     *
     * @throws \LogicException если подходящий runner не найден
     */
    public function resolve(ChainStepTypeEnum $type): StepRunnerInterface
    {
        foreach ($this->runners as $runner) {
            if ($runner->supports($type)) {
                return $runner;
            }
        }

        throw new \LogicException(
            sprintf('No StepRunnerInterface found for step type "%s".', $type->value),
        );
    }
}
