<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainConfigViolationVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionalChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;

/**
 * Domain Service: валидирует определение цепочки оркестрации.
 *
 * Реализует «мягкую» валидацию — собирает все нарушения без выброса исключений.
 * Инварианты определены в одном месте (Domain) и могут переиспользоваться
 * как при pre-flight валидации, так и при создании цепочки.
 *
 * Validator работает с уже сконструированными VO (дополнительный уровень защиты).
 * Guard-проверки в VO (fail-fast при конструировании) остаются без изменений.
 */
final readonly class ChainDefinitionValidator
{
    /**
     * Валидирует определение цепочки и возвращает список нарушений.
     *
     * @return list<ChainConfigViolationVo> пустой список = нарушений нет
     */
    public function validate(ChainDefinitionInterface $chain): array
    {
        if ($chain instanceof DynamicChainDefinitionVo) {
            return $this->validateDynamicChain($chain);
        }

        if ($chain instanceof StaticChainDefinitionVo) {
            return $this->validateStepBasedChain($chain);
        }

        if ($chain instanceof ConditionalChainDefinitionVo) {
            return $this->validateStepBasedChain($chain);
        }

        return [];
    }

    /**
     * Валидирует static/conditional-цепочку (step-based).
     *
     * @param StaticChainDefinitionVo|ConditionalChainDefinitionVo $chain
     * @return list<ChainConfigViolationVo>
     */
    private function validateStepBasedChain(StaticChainDefinitionVo|ConditionalChainDefinitionVo $chain): array
    {
        $violations = [];
        $name = $chain->getName();
        $steps = $chain->getSteps();

        if ($steps === []) {
            $violations[] = new ChainConfigViolationVo(
                chainName: $name,
                field: 'steps',
                message: sprintf('Static chain "%s" must have at least one step.', $name),
            );

            return $violations;
        }

        foreach ($steps as $i => $step) {
            $violations = [...$violations, ...$this->validateStep($name, $i, $step)];
        }

        // fix_iterations: ссылки на существующие шаги
        foreach ($chain->getFixIterations() as $group) {
            $stepNameMap = [];
            foreach ($steps as $step) {
                $stepName = $step->getName();
                if ($stepName !== null) {
                    $stepNameMap[$stepName] = true;
                }
            }

            foreach ($group->getStepNames() as $stepName) {
                if (!isset($stepNameMap[$stepName])) {
                    $violations[] = new ChainConfigViolationVo(
                        chainName: $name,
                        field: 'fix_iterations',
                        message: sprintf(
                            'fix_iteration group "%s" references unknown step "%s".',
                            $group->getGroup(),
                            $stepName,
                        ),
                    );
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<ChainConfigViolationVo>
     */
    private function validateDynamicChain(DynamicChainDefinitionVo $chain): array
    {
        $violations = [];
        $name = $chain->getName();

        $facilitator = $chain->getFacilitator();
        if ($facilitator === '') {
            $violations[] = new ChainConfigViolationVo(
                chainName: $name,
                field: 'facilitator',
                message: sprintf('Dynamic chain "%s" must specify a facilitator.', $name),
            );
        }

        $participants = $chain->getParticipants();
        if ($participants === []) {
            $violations[] = new ChainConfigViolationVo(
                chainName: $name,
                field: 'participants',
                message: sprintf('Dynamic chain "%s" must have at least one participant.', $name),
            );
        }

        if ($chain->getMaxRounds() < 1) {
            $violations[] = new ChainConfigViolationVo(
                chainName: $name,
                field: 'max_rounds',
                message: sprintf('Dynamic chain "%s" max_rounds must be >= 1.', $name),
            );
        }

        return $violations;
    }

    /**
     * @return list<ChainConfigViolationVo>
     */
    private function validateStep(string $chainName, int $index, ChainStepVo $step): array
    {
        $violations = [];
        $fieldPrefix = sprintf('steps[%d]', $index);

        if ($step->isAgent()) {
            $role = $step->getRole();
            if ($role === null || $role === '') {
                $violations[] = new ChainConfigViolationVo(
                    chainName: $chainName,
                    field: $fieldPrefix . '.role',
                    message: sprintf('Step %d: agent step must have a role.', $index + 1),
                );
            }
        } elseif ($step->isQualityGate()) {
            $command = $step->getCommand();
            if ($command === '') {
                $violations[] = new ChainConfigViolationVo(
                    chainName: $chainName,
                    field: $fieldPrefix . '.command',
                    message: sprintf('Step %d: quality_gate step must have a command.', $index + 1),
                );
            }

            $label = $step->getLabel();
            if ($label === '') {
                $violations[] = new ChainConfigViolationVo(
                    chainName: $chainName,
                    field: $fieldPrefix . '.label',
                    message: sprintf('Step %d: quality_gate step must have a label.', $index + 1),
                );
            }
        }

        return $violations;
    }
}
