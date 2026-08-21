<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\QueryBus;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use TaskOrchestrator\Common\Component\QueryBus\QueryBus;
use TaskOrchestrator\Common\Component\QueryBus\QueryBusException;

#[CoversClass(QueryBus::class)]
final class QueryBusTest extends TestCase
{
    #[Test]
    public function queryDispatchesQueryToRegisteredHandler(): void
    {
        $handler = new class {
            public function __invoke(object $query): object
            {
                return new class {
                    public string $answered = 'yes';
                };
            }
        };

        $bus = new QueryBus(new ServiceLocator([
            StubQuery::class => static fn (): object => $handler,
        ]));

        $result = $bus->query(new StubQuery());

        self::assertSame('yes', $result->answered);
    }

    #[Test]
    public function queryThrowsForUnknownQuery(): void
    {
        $bus = new QueryBus(new ServiceLocator([]));

        $this->expectException(QueryBusException::class);
        $this->expectExceptionMessage(StubQuery::class);

        $bus->query(new StubQuery());
    }

    #[Test]
    public function queryThrowsForNonCallableHandler(): void
    {
        $bus = new QueryBus(new ServiceLocator([
            StubQuery::class => static fn (): object => new \stdClass(),
        ]));

        $this->expectException(QueryBusException::class);
        $this->expectExceptionMessage('is not callable');

        $bus->query(new StubQuery());
    }
}

final class StubQuery
{
}
