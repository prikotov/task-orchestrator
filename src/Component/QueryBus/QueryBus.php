<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\QueryBus;

use Override;
use Psr\Container\ContainerInterface;

/**
 * Реализация QueryBus на PSR-11 локаторе «класс запроса => Query Handler».
 *
 * Локатор собирается компилятор-пасом
 * {@see \TaskOrchestrator\Common\Component\CommandBus\DependencyInjection\UseCaseBusCompilerPass}
 * по факту зарегистрированных Use Case Handler-ов — ручная конфигурация не нужна.
 * Ленивое разрешение ссылок исключает круговые зависимости контейнера.
 */
final class QueryBus implements QueryBusComponentInterface
{
    public function __construct(
        private readonly ContainerInterface $handlers,
    ) {
    }

    #[Override]
    public function query(object $query): mixed
    {
        $queryClass = $query::class;

        if (!$this->handlers->has($queryClass)) {
            throw new QueryBusException(sprintf('No Query Handler registered for query %s.', $queryClass));
        }

        $handler = $this->handlers->get($queryClass);

        if (!is_callable($handler)) {
            throw new QueryBusException(sprintf('Query Handler for query %s is not callable.', $queryClass));
        }

        return $handler($query);
    }
}
