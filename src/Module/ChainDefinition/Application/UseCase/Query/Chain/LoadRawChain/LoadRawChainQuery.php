<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain;

/**
 * Запрос на загрузку «сырого» определения цепочки (Domain VO).
 *
 * В отличие от LoadChainQuery (который возвращает DTO для Presentation),
 * этот запрос возвращает Domain-объект ChainDefinitionInterface —
 * для использования Integration-слоём других модулей (Integration → foreign Application).
 */
final readonly class LoadRawChainQuery
{
    public function __construct(
        public string $chainName,
        public ?string $configPath = null,
    ) {
    }
}
