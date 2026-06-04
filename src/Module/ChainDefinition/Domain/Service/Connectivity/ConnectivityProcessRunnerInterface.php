<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Connectivity;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityProcessRequestVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityProcessResultVo;

/**
 * Контракт запуска процесса проверки связности роли.
 */
interface ConnectivityProcessRunnerInterface
{
    public function run(ConnectivityProcessRequestVo $request): ConnectivityProcessResultVo;
}
