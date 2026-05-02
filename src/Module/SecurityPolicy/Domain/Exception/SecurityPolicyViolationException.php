<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception;

/**
 * Нарушение chain-level security policy.
 *
 * Выбрасывается, когда цепочка не авторизована для выполнения
 * (имя цепочки или тип не входит в разрешённые).
 */
final class SecurityPolicyViolationException extends SecurityPolicyException
{
    public function __construct(
        string $chainName,
        string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Security policy violation for chain "%s": %s', $chainName, $reason),
            0,
            $previous,
        );
    }
}
