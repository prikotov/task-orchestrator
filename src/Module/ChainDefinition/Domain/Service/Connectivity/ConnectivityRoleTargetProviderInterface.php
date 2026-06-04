<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Connectivity;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityRoleTargetVo;

/**
 * Контракт чтения top-level `roles` из конфигурации цепочек для проверки запуска ролей из chains.yaml.
 */
interface ConnectivityRoleTargetProviderInterface
{
    /**
     * @return list<ConnectivityRoleTargetVo>
     */
    public function list(?string $configPath = null): array;
}
