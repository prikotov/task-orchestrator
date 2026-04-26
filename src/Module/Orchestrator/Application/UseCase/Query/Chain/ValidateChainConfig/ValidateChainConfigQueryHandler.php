<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\ValidateChainConfig;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper\ChainConfigViolationDtoMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\ChainDefinitionValidator;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;

/**
 * Валидирует конфигурацию цепочки (или всех цепочек).
 *
 * Если указан configPath — переопределяет путь к chains.yaml перед загрузкой.
 */
class ValidateChainConfigQueryHandler
{
    public function __construct(
        private ChainLoaderInterface $chainLoader,
        private ChainDefinitionValidator $chainValidator,
        private ChainConfigViolationDtoMapper $violationMapper,
    ) {
    }

    public function __invoke(ValidateChainConfigQuery $query): ValidateChainConfigResult
    {
        if ($query->configPath !== null) {
            $this->chainLoader->overridePath($query->configPath);
        }

        if ($query->chainName !== null) {
            return $this->validateSpecificChain($query->chainName);
        }

        return $this->validateAllChains();
    }

    private function validateSpecificChain(string $chainName): ValidateChainConfigResult
    {
        $chainVo = $this->chainLoader->load($chainName);
        $violations = $this->chainValidator->validate($chainVo);

        return new ValidateChainConfigResult(
            isValid: $violations === [],
            violations: $this->violationMapper->mapList($violations),
            validChainName: $chainName,
        );
    }

    private function validateAllChains(): ValidateChainConfigResult
    {
        $chains = $this->chainLoader->list();
        $chainNames = array_keys($chains);

        $allViolations = [];
        foreach ($chains as $chain) {
            $chainViolations = $this->chainValidator->validate($chain);
            $allViolations = [...$allViolations, ...$chainViolations];
        }

        return new ValidateChainConfigResult(
            isValid: $allViolations === [],
            violations: $this->violationMapper->mapList($allViolations),
            chainNames: $chainNames,
        );
    }
}
