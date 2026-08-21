<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\CommandBus;

/**
 * Ошибка диспетчеризации команды: команда не зарегистрирована
 * или Command Handler нарушил контракт вызова.
 */
final class CommandBusException extends \RuntimeException
{
}
