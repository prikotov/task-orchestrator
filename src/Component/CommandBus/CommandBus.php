<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\CommandBus;

use Override;
use Psr\Container\ContainerInterface;

/**
 * Реализация CommandBus на PSR-11 локаторе «класс команды => Command Handler».
 *
 * Локатор собирается компилятор-пасом
 * {@see \TaskOrchestrator\Common\Component\CommandBus\DependencyInjection\UseCaseBusCompilerPass}
 * по факту зарегистрированных Use Case Handler-ов — ручная конфигурация не нужна.
 * Ленивое разрешение ссылок исключает круговые зависимости контейнера
 * (Application-хендлер → Integration-сервис → шина → хендлер).
 */
final class CommandBus implements CommandBusComponentInterface
{
    public function __construct(
        private readonly ContainerInterface $handlers,
    ) {
    }

    #[Override]
    public function execute(object $command): mixed
    {
        $commandClass = $command::class;

        if (!$this->handlers->has($commandClass)) {
            throw new CommandBusException(sprintf('No Command Handler registered for command %s.', $commandClass));
        }

        $handler = $this->handlers->get($commandClass);

        if (!is_callable($handler)) {
            throw new CommandBusException(sprintf('Command Handler for command %s is not callable.', $commandClass));
        }

        return $handler($command);
    }
}
