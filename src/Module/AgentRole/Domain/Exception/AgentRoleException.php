<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Exception;

/**
 * Базовое исключение модуля AgentRole.
 *
 * Все публичные ошибки модуля являются потомками этого класса, что позволяет
 * ловить единый базовый класс на уровне Application (boundary-контракт
 * исключений: наружу выбрасываются только исключения своего слоя).
 */
class AgentRoleException extends \RuntimeException
{
}
