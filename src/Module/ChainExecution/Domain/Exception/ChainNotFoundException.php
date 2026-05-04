<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Exception;

/**
 * Chain not found by name.
 */
final class ChainNotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{
    public function __construct(string $chainName, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Chain "%s" not found.', $chainName), $code, $previous);
    }
}
