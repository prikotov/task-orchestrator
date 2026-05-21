<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;

/**
 * Фабрика: резолвит ExecuteStepServiceInterface по ChainStepTypeEnum.
 *
 * Принимает iterable<ExecuteStepServiceInterface> (tagged services),
 * находит подходящий сервис по supports().
 */
final readonly class ResolveStepRunnerService implements ResolveStepRunnerServiceInterface
{
    /**
     * @param iterable<ExecuteStepServiceInterface> $runners
     */
    public function __construct(
        private iterable $runners,
    ) {
    }

    #[Override]
    public function resolve(ChainStepTypeEnum $type): ExecuteStepServiceInterface
    {
        foreach ($this->runners as $runner) {
            if ($runner->supports($type)) {
                return $runner;
            }
        }

        throw new LogicException(
            sprintf('No ExecuteStepServiceInterface found for step type "%s".', $type->value),
        );
    }
}
