<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Connectivity;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityProcessRequestVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityProcessResultVo;

/**
 * Контракт запуска процесса для проверки запуска роли из chains.yaml.
 */
interface ConnectivityProcessRunnerInterface
{
    public function run(ConnectivityProcessRequestVo $request): ConnectivityProcessResultVo;
}
