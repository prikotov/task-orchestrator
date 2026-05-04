<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Execution VO: описание quality gate для выполнения.
 *
 * Маппится из ChainDefinition\Domain\ValueObject\QualityGateVo через Integration-маппер.
 */
final readonly class ExecutionQualityGateVo
{
    public function __construct(
        public string $command,
        public string $label,
        public int $timeoutSeconds = 120,
    ) {
        if (trim($command) === '') {
            throw new InvalidArgumentException('ExecutionQualityGateVo::command must not be empty.');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException('ExecutionQualityGateVo::label must not be empty.');
        }
    }
}
