<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\ValidateChainConfig;

use TaskOrchestrator\Common\Module\ChainDefinition\Application\Mapper\ChainConfigViolationDtoMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\ChainConfigExceptionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\InvalidFixIterationsException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainDefinitionValidatorService;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\CollectFixIterationsViolationsServiceInterface;

/**
 * Валидирует конфигурацию цепочки (или всех цепочек).
 *
 * Если указан configPath — переопределяет путь к chains.yaml перед загрузкой.
 *
 * Подход D′: если load() падает на domain-исключении конфигурации
 * ({@see ChainConfigExceptionInterface}), Handler получает detailed-диагностику
 * по raw-входным данным носителя через единый источник — коллектор
 * ({@see CollectFixIterationsViolationsServiceInterface}). Это чинит «слепую зону №2»
 * (подробная диагностика fix-итераций была структурно недостижима в validate-пути,
 * т.к. factory fail-fast падала раньше валидатора). Run-путь не затрагивается —
 * исключение по-прежнему летит дальше для боевых сценариев вне validate-конфигурации.
 */
class ValidateChainConfigQueryHandler
{
    public function __construct(
        private ChainLoaderInterface $chainLoader,
        private ChainDefinitionValidatorService $chainValidator,
        private ChainConfigViolationDtoMapper $violationMapper,
        private CollectFixIterationsViolationsServiceInterface $fixIterationsViolationsCollector,
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
        try {
            $chainVo = $this->chainLoader->load($chainName);
        } catch (ChainConfigExceptionInterface $e) {
            // Factory fail-fast падает раньше валидатора; перехватываем carrier-исключение
            // по маркерному интерфейсу и достаиваем detailed-нарушения по raw-данным.
            $violations = $e instanceof InvalidFixIterationsException
                ? $this->fixIterationsViolationsCollector->collect(
                    $e->getChainName(),
                    $e->getSteps(),
                    $e->getFixIterations(),
                )
                : [];

            return new ValidateChainConfigResult(
                isValid: false,
                violations: $this->violationMapper->mapList($violations),
                validChainName: $chainName,
            );
        }

        $violations = $this->chainValidator->validate($chainVo);

        return new ValidateChainConfigResult(
            isValid: $violations === [],
            violations: $this->violationMapper->mapList($violations),
            validChainName: $chainName,
        );
    }

    private function validateAllChains(): ValidateChainConfigResult
    {
        // list() парсит все цепочки; при невалидных fix-итерациях любой из них factory
        // fail-fast бросает ChainConfigExceptionInterface. Перехватываем и показываем
        // detailed-нарушения упавшей цепочки вместо generic-краша всего прохода.
        // Полный охват validate-all (без обрыва на первой невалидной) вне scope —
        // требует parser extraction (см. design §6/§7.2).
        try {
            $chains = $this->chainLoader->list();
        } catch (ChainConfigExceptionInterface $e) {
            $violations = $e instanceof InvalidFixIterationsException
                ? $this->fixIterationsViolationsCollector->collect(
                    $e->getChainName(),
                    $e->getSteps(),
                    $e->getFixIterations(),
                )
                : [];

            return new ValidateChainConfigResult(
                isValid: false,
                violations: $this->violationMapper->mapList($violations),
            );
        }

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
