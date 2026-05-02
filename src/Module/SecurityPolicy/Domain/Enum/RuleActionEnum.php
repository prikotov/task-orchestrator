<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum;

/**
 * Действие правила безопасности: разрешить или запретить.
 *
 * Deny-first: если хотя бы одно deny-правило совпадает → denied,
 * даже если есть allow для более широкого паттерна.
 */
enum RuleActionEnum: string
{
    case allow = 'allow';
    case deny = 'deny';
}
