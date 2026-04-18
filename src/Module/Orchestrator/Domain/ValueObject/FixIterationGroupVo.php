<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object группы итераций фикса (fix iteration group).
 *
 * Определяет именованную группу шагов, образующих итерационный цикл:
 * если последний шаг группы завершается — цепочка прыгает к первому шагу группы.
 *
 * Валидируется при создании:
 * - имя группы непустое
 * - ≥ 2 шагов в группе
 * - нет дубликатов имён шагов
 * - maxIterations ≥ 1
 */
final readonly class FixIterationGroupVo
{
    /**
     * @param string $group имя группы (уникальное в рамках цепочки)
     * @param list<string> $stepNames имена шагов (ссылаются на ChainStepVo.name)
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

        $unique = array_unique($stepNames);
        if (count($unique) !== count($stepNames)) {
            throw new InvalidArgumentException(
                sprintf('Fix iteration group "%s" has duplicate step names.', $group),
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

    /**
     * Является ли данное имя шага первым в группе?
     */
    public function isFirstStep(string $stepName): bool
    {
        return $this->stepNames[0] === $stepName;
    }

    /**
     * Является ли данное имя шага последним в группе?
     */
    public function isLastStep(string $stepName): bool
    {
        $count = count($this->stepNames);
        assert($count > 0);
        $last = $this->stepNames[$count - 1];

        return $last === $stepName;
    }

    /**
     * Содержит ли группа шаг с данным именем?
     */
    public function containsStep(string $stepName): bool
    {
        return in_array($stepName, $this->stepNames, true);
    }
}
