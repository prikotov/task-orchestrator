<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\CommandBus\CommandBus;
use TaskOrchestrator\Common\Component\CommandBus\CommandBusComponentInterface;
use TaskOrchestrator\Common\Component\CommandBus\DependencyInjection\UseCaseBusCompilerPass;
use TaskOrchestrator\Common\Component\QueryBus\QueryBus;
use TaskOrchestrator\Common\Component\QueryBus\QueryBusComponentInterface;
use TaskOrchestrator\Common\Kernel;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersResultDto;

/**
 * Интеграционная проверка проводки Use Case шин в реальном контейнере Kernel.
 *
 * Гарантирует, что UseCaseBusCompilerPass связал зарегистрированные хендлеры
 * со шинами: алиасы интерфейсов разрешаются, диспатч реального запроса
 * возвращает его DTO, неизвестное сообщение даёт понятное исключение.
 */
#[CoversClass(UseCaseBusCompilerPass::class)]
#[CoversClass(CommandBus::class)]
#[CoversClass(QueryBus::class)]
final class UseCaseBusWiringIntegrationTest extends TestCase
{
    #[Test]
    public function containerWiresQueryBusAndDispatchesRealQuery(): void
    {
        $kernel = new Kernel('test', false);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();

            $queryBus = $container->get(QueryBusComponentInterface::class);
            self::assertInstanceOf(QueryBus::class, $queryBus);

            /** @var GetRunnersResultDto $result */
            $result = $queryBus->query(new GetRunnersQuery());

            self::assertInstanceOf(GetRunnersResultDto::class, $result);
            self::assertNotSame([], $result->runners);
        } finally {
            $kernel->shutdown();
        }
    }

    #[Test]
    public function containerWiresCommandBusAlias(): void
    {
        $kernel = new Kernel('test', false);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();

            self::assertInstanceOf(
                CommandBus::class,
                $container->get(CommandBusComponentInterface::class),
            );
        } finally {
            $kernel->shutdown();
        }
    }

    #[Test]
    public function unknownQueryFailsWithReadableException(): void
    {
        $kernel = new Kernel('test', false);
        $kernel->boot();

        try {
            $queryBus = $kernel->getContainer()->get(QueryBusComponentInterface::class);
            \assert($queryBus instanceof QueryBusComponentInterface);

            $unknownQuery = new class {};

            try {
                $queryBus->query($unknownQuery);
                self::fail('QueryBusException was not thrown.');
            } catch (\TaskOrchestrator\Common\Component\QueryBus\QueryBusException $e) {
                self::assertStringContainsString($unknownQuery::class, $e->getMessage());
            }
        } finally {
            $kernel->shutdown();
        }
    }
}
