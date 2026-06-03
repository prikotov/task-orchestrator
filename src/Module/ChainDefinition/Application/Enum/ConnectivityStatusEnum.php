<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\Enum;

/**
 * Status (статус) проверки связности роли.
 */
enum ConnectivityStatusEnum: string
{
    case ok = 'ok';
    case fail = 'fail';
    case timeout = 'timeout';
    case dryRun = 'dry_run';
}
