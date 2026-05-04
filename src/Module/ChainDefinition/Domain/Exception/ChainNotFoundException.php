<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\NotFoundExceptionInterface;

final class ChainNotFoundException extends OrchestratorException implements NotFoundExceptionInterface
{
    public function __construct(string $chainName, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Chain "%s" not found.', $chainName), 0, $previous);
    }
}
