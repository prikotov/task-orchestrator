<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\Enum;

/**
 * Status (статус) проверки запуска роли из chains.yaml.
 */
enum ConnectivityStatusEnum: string
{
    case ok = 'ok';
    case fail = 'fail';
    case timeout = 'timeout';
    case dryRun = 'dry_run';
}
