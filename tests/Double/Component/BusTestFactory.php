<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Double\Component;

use ReflectionNamedType;
use ReflectionObject;
use Symfony\Component\DependencyInjection\ServiceLocator;
use TaskOrchestrator\Common\Component\CommandBus\CommandBus;
use TaskOrchestrator\Common\Component\CommandBus\CommandBusComponentInterface;
use TaskOrchestrator\Common\Component\QueryBus\QueryBus;
use TaskOrchestrator\Common\Component\QueryBus\QueryBusComponentInterface;

/**
 * Строит CommandBus/QueryBus для тестов из готовых хендлеров.
 *
 * Ключ локатора «класс сообщения => хендлер» вычисляется рефлексией `__invoke` —
 * тот же принцип, что у UseCaseBusCompilerPass в production-проводке.
 */
final class BusTestFactory
{
    /**
     * @param object ...$handlers Command Handler-ы (или их моки)
     */
    public static function commandBus(object ...$handlers): CommandBusComponentInterface
    {
        return new CommandBus(new ServiceLocator(self::locatorMap($handlers)));
    }

    /**
     * @param object ...$handlers Query Handler-ы (или их моки)
     */
    public static function queryBus(object ...$handlers): QueryBusComponentInterface
    {
        return new QueryBus(new ServiceLocator(self::locatorMap($handlers)));
    }

    /**
     * @param list<object> $handlers
     *
     * @return array<class-string, callable(): object>
     */
    private static function locatorMap(array $handlers): array
    {
        $map = [];

        foreach ($handlers as $handler) {
            $type = (new ReflectionObject($handler))
                ->getMethod('__invoke')
                ->getParameters()[0]
                ->getType();

            if (!$type instanceof ReflectionNamedType) {
                throw new \InvalidArgumentException(
                    sprintf('Handler %s must declare a single typed message in __invoke.', $handler::class),
                );
            }

            $messageClass = $type->getName();
            $map[$messageClass] = static fn (): object => $handler;
        }

        return $map;
    }
}
