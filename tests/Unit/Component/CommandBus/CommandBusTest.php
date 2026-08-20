<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\CommandBus;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use TaskOrchestrator\Common\Component\CommandBus\CommandBus;
use TaskOrchestrator\Common\Component\CommandBus\CommandBusException;

#[CoversClass(CommandBus::class)]
final class CommandBusTest extends TestCase
{
    #[Test]
    public function executeDispatchesCommandToRegisteredHandler(): void
    {
        $handler = new class {
            public function __invoke(object $command): object
            {
                return new class {
                    public string $handled = 'yes';
                };
            }
        };

        $bus = new CommandBus(new ServiceLocator([
            StubCommand::class => static fn (): object => $handler,
        ]));

        $result = $bus->execute(new StubCommand());

        self::assertSame('yes', $result->handled);
    }

    #[Test]
    public function executeThrowsForUnknownCommand(): void
    {
        $bus = new CommandBus(new ServiceLocator([]));

        $this->expectException(CommandBusException::class);
        $this->expectExceptionMessage(StubCommand::class);

        $bus->execute(new StubCommand());
    }

    #[Test]
    public function executeThrowsForNonCallableHandler(): void
    {
        $bus = new CommandBus(new ServiceLocator([
            StubCommand::class => static fn (): object => new \stdClass(),
        ]));

        $this->expectException(CommandBusException::class);
        $this->expectExceptionMessage('is not callable');

        $bus->execute(new StubCommand());
    }

    #[Test]
    public function executeAllowsScalarHandlerResult(): void
    {
        $handler = new class {
            public function __invoke(object $command): string
            {
                return 'scalar-result';
            }
        };

        $bus = new CommandBus(new ServiceLocator([
            StubCommand::class => static fn (): object => $handler,
        ]));

        self::assertSame('scalar-result', $bus->execute(new StubCommand()));
    }
}

final class StubCommand
{
}
