<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Execution VO: группа итераций фикса.
 *
 * Маппится из ChainDefinition\Domain\ValueObject\FixIterationGroupVo через Integration-маппер.
 */
final readonly class ExecutionFixIterationGroupVo
{
    /**
     * @param string $group имя группы
     * @param list<string> $stepNames имена шагов
     * @param int $maxIterations лимит итераций (≥ 1)
     */
    public function __construct(
        private string $group,
        private array $stepNames,
        private int $maxIterations = 3,
    ) {
        if ($group === '') {
            throw new InvalidArgumentException('Fix iteration group name must not be empty.');
        }

        if (count($stepNames) < 2) {
            throw new InvalidArgumentException(
                sprintf('Fix iteration group "%s" must have at least 2 step names, got %d.', $group, count($stepNames)),
            );
        }

        if ($maxIterations < 1) {
            throw new InvalidArgumentException(
                sprintf('Fix iteration group "%s" max_iterations must be ≥ 1, got %d.', $group, $maxIterations),
            );
        }
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * @return list<string>
     */
    public function getStepNames(): array
    {
        return $this->stepNames;
    }

    public function getMaxIterations(): int
    {
        return $this->maxIterations;
    }

    public function isFirstStep(string $stepName): bool
    {
        return $this->stepNames[0] === $stepName;
    }

    public function isLastStep(string $stepName): bool
    {
        $count = count($this->stepNames);
        assert($count > 0);

        return $this->stepNames[$count - 1] === $stepName;
    }

    public function containsStep(string $stepName): bool
    {
        return in_array($stepName, $this->stepNames, true);
    }
}
