<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\CommandBus\DependencyInjection;

use Override;
use ReflectionNamedType;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use TaskOrchestrator\Common\Component\CommandBus\CommandBus;
use TaskOrchestrator\Common\Component\QueryBus\QueryBus;


/**
 * Связывает Use Case Handler-ы со шинами CommandBus/QueryBus.
 *
 * Один пас обслуживает обе шины (единая задача — диспетчеризация Use Case),
 * поэтому лежит в Component\CommandBus\DependencyInjection.
 *
 * Подход — PHAR-safe рефлексия по уже зарегистрированным определениям сервисов
 * (тот же принцип, что у ModuleServiceRegistrar): ручные теги или конфигурация
 * в module-local services.yaml не нужны — любой новый invokable-хендлер
 * подхватывается автоматически.
 *
 * Критерий хендлера:
 *  - класс зарегистрирован как сервис и оканчивается на `Handler`;
 *  - FQCN содержит `\Application\UseCase\Command\` либо `\Application\UseCase\Query\`;
 *  - имеет единственный `__invoke` с одним типизированным объектным параметром
 *    (класс сообщения становится ключом локатора).
 *
 * Хендлеры без `__invoke` (например, RunAgentQueryHandler с методом `run()`)
 * игнорируются — они не участвуют в диспетчеризации и не флагуются
 * PHPStan-правилом прямого вызова.
 *
 * Круговые зависимости исключены: шины получают ServiceLocator с ленивыми
 * Reference-ами (Application-хендлер → Integration-сервис → шина → хендлер).
 */
final class UseCaseBusCompilerPass implements CompilerPassInterface
{
    private const string COMMAND_NAMESPACE = '\Application\UseCase\Command\\';

    private const string QUERY_NAMESPACE = '\Application\UseCase\Query\\';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $commandMap = [];
        $queryMap = [];

        foreach ($container->getDefinitions() as $serviceId => $definition) {
            $handlerClass = $definition->getClass();

            if (!is_string($handlerClass)) {
                continue;
            }

            if (!str_ends_with($handlerClass, 'Handler')) {
                continue;
            }

            $messageClass = $this->resolveMessageClass($container, $handlerClass);

            if ($messageClass === null) {
                continue;
            }

            if (str_contains($handlerClass, self::COMMAND_NAMESPACE)) {
                $commandMap[$messageClass] = new Reference($serviceId);
            } elseif (str_contains($handlerClass, self::QUERY_NAMESPACE)) {
                $queryMap[$messageClass] = new Reference($serviceId);
            }
        }

        if ($container->hasDefinition(CommandBus::class)) {
            $container->getDefinition(CommandBus::class)
                ->setArgument(
                    '$handlers',
                    $this->registerServiceLocator($container, 'task_orchestrator.command_bus.handlers', $commandMap),
                );
        }

        if ($container->hasDefinition(QueryBus::class)) {
            $container->getDefinition(QueryBus::class)
                ->setArgument(
                    '$handlers',
                    $this->registerServiceLocator($container, 'task_orchestrator.query_bus.handlers', $queryMap),
                );
        }
    }

    /**
     * Регистрирует сервис-локатор «класс сообщения => Reference хендлера».
     *
     * Эквивалент статического ServiceLocatorTagPass::register() без статического
     * вызова (phpmd StaticAccess): Definition с тегом container.service_locator —
     * Symfony компилирует его в PSR-11 локатор с ленивым разрешением ссылок.
     *
     * @param array<string, Reference> $map
     */
    private function registerServiceLocator(
        ContainerBuilder $container,
        string $serviceId,
        array $map,
    ): Reference {
        $locator = (new Definition(ServiceLocator::class))
            ->addArgument($map)
            ->addTag('container.service_locator');

        $container->setDefinition($serviceId, $locator);

        return new Reference($serviceId);
    }

    /**
     * Класс сообщения из единственного параметра `__invoke` хендлера.
     *
     * Возвращает null, если класс не является Use Case Handler-ом: нет `__invoke`,
     * больше одного параметра или параметр без объектного типа.
     */
    private function resolveMessageClass(ContainerBuilder $container, string $handlerClass): ?string
    {
        $reflection = $container->getReflectionClass($handlerClass);

        if ($reflection === null || !$reflection->hasMethod('__invoke')) {
            return null;
        }

        $parameters = $reflection->getMethod('__invoke')->getParameters();

        if (count($parameters) !== 1) {
            return null;
        }

        return $this->parameterClass($parameters[0]);
    }

    /**
     * @param \ReflectionParameter $parameter первый параметр `__invoke`
     */
    private function parameterClass(\ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        return $type->getName();
    }
}
