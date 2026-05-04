<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Exception;

/**
 * Marker interface for «not found» domain exceptions.
 *
 * Allows Application/Presentation layers to catch all not-found errors uniformly.
 */
interface NotFoundExceptionInterface extends \Throwable
{
}
