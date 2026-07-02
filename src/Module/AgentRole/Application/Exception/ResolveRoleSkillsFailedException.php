<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Application\Exception;

use RuntimeException;



/**
 * Boundary-исключение UseCase резолвинга skills роли.
 *
 * По конвенции исключений наружу (в Presentation) выбрасываются только
 * исключения своего слоя: доменные {@see \TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\AgentRoleException}
 * оборачиваются сюда, чтобы Presentation не зависел от Domain.
 */
final class ResolveRoleSkillsFailedException extends RuntimeException
{
    public static function fromDomain(\Throwable $previous): self
    {
        return new self(
            message: sprintf('Failed to resolve role skills: %s', $previous->getMessage()),
            previous: $previous,
        );
    }
}
