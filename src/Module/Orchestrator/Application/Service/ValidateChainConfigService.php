<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigValidationErrorDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigValidationResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\ChainDefinitionValidator;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainConfigViolationVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;

/**
 * Валидирует конфигурацию цепочек оркестрации (runtime-валидация).
 *
 * Application-сервис: зависит от Domain ChainLoaderInterface и ChainDefinitionValidator.
 * Presentation-слой (OrchestrateCommand) делегирует валидацию сюда.
 *
 * Проверяет:
 * - YAML-структура и парсинг (через ChainLoader)
 * - Обязательные поля цепочек и шагов (через Domain Validator)
 */
final readonly class ValidateChainConfigService implements ValidateChainConfigServiceInterface
{
    private const string GLOBAL_CONTEXT = '__global__';

    public function __construct(
        private ChainLoaderInterface $chainLoader,
        private ChainDefinitionValidator $validator,
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
            $chainErrors = $this->validateChainDefinition($chain);
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

        $errors = $this->validateChainDefinition($chain);

        return new ChainConfigValidationResultDto(
            isValid: $errors === [],
            errors: $errors,
            validatedChains: [$chainName],
        );
    }

    /**
     * Валидирует определение цепочки через Domain Validator
     * и маппит ViolationVo → ErrorDto.
     *
     * @return list<ChainConfigValidationErrorDto>
     */
    private function validateChainDefinition(ChainDefinitionVo $chain): array
    {
        $violations = $this->validator->validate($chain);

        return array_map(
            static fn(ChainConfigViolationVo $v): ChainConfigValidationErrorDto => new ChainConfigValidationErrorDto(
                chainName: $v->getChainName(),
                message: $v->getMessage(),
                field: $v->getField(),
            ),
            $violations,
        );
    }
}
