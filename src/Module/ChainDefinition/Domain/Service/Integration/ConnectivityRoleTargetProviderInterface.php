<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Integration;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityRoleTargetVo;

/**
 * Контракт чтения top-level `roles` из конфигурации цепочек для проверки связности.
 */
interface ConnectivityRoleTargetProviderInterface
{
    /**
     * @return list<ConnectivityRoleTargetVo>
     */
    public function list(?string $configPath = null): array;
}
