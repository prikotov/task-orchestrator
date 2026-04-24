<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigValidationErrorDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigValidationResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;

/**
 * Валидирует конфигурацию цепочек оркестрации (runtime-валидация).
 *
 * Application-сервис: зависит от Domain ChainLoaderInterface (Application → Domain — разрешено).
 * Presentation-слой (OrchestrateCommand) делегирует валидацию сюда.
 *
 * Проверяет:
 * - YAML-структура и парсинг (через ChainLoader)
 * - Обязательные поля цепочек (steps для static, facilitator/participants/prompts для dynamic)
 * - Типы шагов (agent/quality_gate) и обязательные поля шагов
 * - Корректность fix_iterations (ссылки на существующие шаги)
 */
final readonly class ValidateChainConfigService implements ValidateChainConfigServiceInterface
{
    private const string GLOBAL_CONTEXT = '__global__';

    public function __construct(
        private ChainLoaderInterface $chainLoader,
    ) {
    }

    #[Override]
    public function validateAll(): ChainConfigValidationResultDto
    {
        $errors = [];

        try {
            $chains = $this->chainLoader->list();
        } catch (\Exception $e) {
            return new ChainConfigValidationResultDto(
                isValid: false,
                errors: [new ChainConfigValidationErrorDto(
                    chainName: self::GLOBAL_CONTEXT,
                    message: sprintf('Failed to load chains configuration: %s', $e->getMessage()),
                )],
                validatedChains: [],
            );
        }

        if ($chains === []) {
            return new ChainConfigValidationResultDto(
                isValid: false,
                errors: [new ChainConfigValidationErrorDto(
                    chainName: self::GLOBAL_CONTEXT,
                    message: 'No chains defined in configuration.',
                )],
                validatedChains: [],
            );
        }

        $validatedChains = [];

        foreach ($chains as $name => $chain) {
            $chainErrors = $this->validateChainDefinition($name, $chain);
            $errors = [...$errors, ...$chainErrors];
            $validatedChains[] = $name;
        }

        return new ChainConfigValidationResultDto(
            isValid: $errors === [],
            errors: $errors,
            validatedChains: $validatedChains,
        );
    }

    #[Override]
    public function validateChain(string $chainName): ChainConfigValidationResultDto
    {
        try {
            $chain = $this->chainLoader->load($chainName);
        } catch (\Exception $e) {
            return new ChainConfigValidationResultDto(
                isValid: false,
                errors: [new ChainConfigValidationErrorDto(
                    chainName: $chainName,
                    message: $e->getMessage(),
                )],
                validatedChains: [],
            );
        }

        $errors = $this->validateChainDefinition($chainName, $chain);

        return new ChainConfigValidationResultDto(
            isValid: $errors === [],
            errors: $errors,
            validatedChains: [$chainName],
        );
    }

    /**
     * Валидирует отдельное определение цепочки.
     *
     * Цепочка уже прошла парсинг в YamlChainLoader, но мы проверяем
     * семантическую корректность на уровне VO (дополнительный уровень защиты).
     *
     * @return list<ChainConfigValidationErrorDto>
     */
    private function validateChainDefinition(string $name, ChainDefinitionVo $chain): array
    {
        $errors = [];

        if ($chain->isDynamic()) {
            $errors = [...$errors, ...$this->validateDynamicChain($name, $chain)];
        } else {
            $errors = [...$errors, ...$this->validateStaticChain($name, $chain)];
        }

        return $errors;
    }

    /**
     * @return list<ChainConfigValidationErrorDto>
     */
    private function validateStaticChain(string $name, ChainDefinitionVo $chain): array
    {
        $errors = [];
        $steps = $chain->getSteps();

        if ($steps === []) {
            $errors[] = new ChainConfigValidationErrorDto(
                chainName: $name,
                message: sprintf('Static chain "%s" must have at least one step.', $name),
                field: 'steps',
            );

            return $errors;
        }

        foreach ($steps as $i => $step) {
            $errors = [...$errors, ...$this->validateStep($name, $i, $step)];
        }

        // Валидируем fix_iterations (дублирует проверку ChainDefinitionVo, но даёт детальные ошибки)
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
                    $errors[] = new ChainConfigValidationErrorDto(
                        chainName: $name,
                        message: sprintf(
                            'fix_iteration group "%s" references unknown step "%s".',
                            $group->getGroup(),
                            $stepName,
                        ),
                        field: 'fix_iterations',
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<ChainConfigValidationErrorDto>
     */
    private function validateDynamicChain(string $name, ChainDefinitionVo $chain): array
    {
        $errors = [];

        $facilitator = $chain->getFacilitator();
        if ($facilitator === null || $facilitator === '') {
            $errors[] = new ChainConfigValidationErrorDto(
                chainName: $name,
                message: sprintf('Dynamic chain "%s" must specify a facilitator.', $name),
                field: 'facilitator',
            );
        }

        $participants = $chain->getParticipants();
        if ($participants === []) {
            $errors[] = new ChainConfigValidationErrorDto(
                chainName: $name,
                message: sprintf('Dynamic chain "%s" must have at least one participant.', $name),
                field: 'participants',
            );
        }

        if ($chain->getMaxRounds() < 1) {
            $errors[] = new ChainConfigValidationErrorDto(
                chainName: $name,
                message: sprintf('Dynamic chain "%s" max_rounds must be >= 1.', $name),
                field: 'max_rounds',
            );
        }

        return $errors;
    }

    /**
     * @return list<ChainConfigValidationErrorDto>
     */
    private function validateStep(string $chainName, int $index, ChainStepVo $step): array
    {
        $errors = [];
        $fieldPrefix = sprintf('steps[%d]', $index);

        if ($step->isAgent()) {
            $role = $step->getRole();
            if ($role === null || $role === '') {
                $errors[] = new ChainConfigValidationErrorDto(
                    chainName: $chainName,
                    message: sprintf('Step %d: agent step must have a role.', $index + 1),
                    field: $fieldPrefix . '.role',
                );
            }
        } elseif ($step->isQualityGate()) {
            $command = $step->getCommand();
            if ($command === '') {
                $errors[] = new ChainConfigValidationErrorDto(
                    chainName: $chainName,
                    message: sprintf('Step %d: quality_gate step must have a command.', $index + 1),
                    field: $fieldPrefix . '.command',
                );
            }

            $label = $step->getLabel();
            if ($label === '') {
                $errors[] = new ChainConfigValidationErrorDto(
                    chainName: $chainName,
                    message: sprintf('Step %d: quality_gate step must have a label.', $index + 1),
                    field: $fieldPrefix . '.label',
                );
            }
        }

        return $errors;
    }
}
