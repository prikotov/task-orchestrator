<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Connectivity;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityResolvedCommandVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityRoleTargetVo;

/**
 * Контракт сборки production-like argv (массив аргументов) для проверки связности роли.
 */
interface ConnectivityCommandResolverInterface
{
    public function resolve(ConnectivityRoleTargetVo $target): ConnectivityResolvedCommandVo;

    /**
     * Удаляет временные файлы, созданные при resolve().
     */
    public function cleanup(ConnectivityResolvedCommandVo $resolvedCommand): void;
}
