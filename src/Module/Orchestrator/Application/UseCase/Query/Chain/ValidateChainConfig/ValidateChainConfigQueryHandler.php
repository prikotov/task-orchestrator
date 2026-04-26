<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\Chain\ValidateChainConfig;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ChainProviderServiceInterface;

/**
 * Валидирует конфигурацию цепочки (или всех цепочек).
 *
 * Если указан configPath — переопределяет путь к chains.yaml перед загрузкой.
 */
class ValidateChainConfigQueryHandler
{
    public function __construct(
        private ChainProviderServiceInterface $chainProvider,
    ) {
    }

    public function __invoke(ValidateChainConfigQuery $query): ValidateChainConfigResult
    {
        if ($query->configPath !== null) {
            $this->chainProvider->overridePath($query->configPath);
        }

        if ($query->chainName !== null) {
            return $this->validateSpecificChain($query->chainName);
        }

        return $this->validateAllChains();
    }

    private function validateSpecificChain(string $chainName): ValidateChainConfigResult
    {
        $chain = $this->chainProvider->load($chainName);
        $violations = $this->chainProvider->validate($chain);

        return new ValidateChainConfigResult(
            isValid: $violations === [],
            violations: $violations,
            validChainName: $chainName,
        );
    }

    private function validateAllChains(): ValidateChainConfigResult
    {
        $chains = $this->chainProvider->list();
        $chainNames = array_keys($chains);

        $allViolations = [];
        foreach ($chains as $chain) {
            $chainViolations = $this->chainProvider->validate($chain);
            $allViolations = [...$allViolations, ...$chainViolations];
        }

        return new ValidateChainConfigResult(
            isValid: $allViolations === [],
            violations: $allViolations,
            chainNames: $chainNames,
        );
    }
}
