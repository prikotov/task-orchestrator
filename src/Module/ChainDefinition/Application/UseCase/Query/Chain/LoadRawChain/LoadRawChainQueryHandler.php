<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainLoaderInterface;

/**
 * Загружает «сырое» определение цепочки (Domain VO).
 *
 * Application-level API для кросс-модульного доступа:
 * Integration-слой другого модуля вызывает этот QueryHandler
 * (Integration → foreign Application — разрешено Deptrac).
 *
 * @see LoadChainQueryHandler — возвращает DTO для Presentation
 */
class LoadRawChainQueryHandler
{
    public function __construct(
        private ChainLoaderInterface $chainLoader,
    ) {
    }

    public function __invoke(LoadRawChainQuery $query): ChainDefinitionInterface
    {
        if ($query->configPath !== null) {
            $this->chainLoader->overridePath($query->configPath);
        }

        return $this->chainLoader->load($query->chainName);
    }
}
