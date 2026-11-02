<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\PharRuntime;

use InvalidArgumentException;
use Override;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * Env-процессор runtime phar-зависимых путей task-orchestrator (prefix `task_orchestrator_phar`).
 *
 * Разрешает параметры ядра `task_orchestrator.package_dir` и
 * `task_orchestrator.phar_path` в момент обращения к параметру, а не при
 * компиляции контейнера (шаг (A) решения П2, устранение класса проблемы
 * «замороженные phar-пути»): значения НЕ запекаются в скомпилированный
 * контейнер, поэтому после перемещения PHAR потребители (layout-резолвер
 * package-fallback, привязка `.phar-binding` команды agent:init) получают пути
 * от ЗАПУЩЕННОГО архива даже при переиспользовании персистентного кеша в
 * `debug=false` — без очистки кеша и без мёртвых phar://-ссылок. Значение
 * кешируется только в пределах экземпляра контейнера (Container::getEnv) —
 * штатное поведение динамических параметров.
 *
 * Имя переменной в плейсхолдере (`%env(task_orchestrator_phar:package_dir)%`
 * и т.п.) — ДИСКРИМИНАТОР ресурса, а НЕ переменная окружения: по образцу
 * процессоров Component/Layout и Component/Locale имя никогда не читается из
 * окружения, замыкание $getEnv не вызывается, задать значение через env нельзя.
 *
 * ❗ Контракт конструктора: аргументы env-процессора — ТОЛЬКО plain-параметры
 * контейнера либо сервисы БЕЗ env-зависимостей. Запрещено передавать в
 * аргументы env-плейсхолдеры (%env(...)%): процессор создаётся контейнером при
 * разрешении env-переменной, поэтому %env()% в аргументах приводит к рекурсии
 * Container::getEnv() (ParameterCircularReferenceException). Провайдер путей
 * ({@see PharRuntimePathsProviderInterface}) внедряется как alias на сервис
 * `kernel` (config/services.yaml) — у ядра нет env-зависимых аргументов,
 * поэтому контракт соблюдён; правило распространяется и на будущие зависимости
 * процессора.
 */
final class TaskOrchestratorPharEnvVarProcessor implements EnvVarProcessorInterface
{
    /** Prefix плейсхолдера `%env(task_orchestrator_phar:package_dir)%` и др. */
    public const string ENV_PREFIX = 'task_orchestrator_phar';

    /** Дискриминатор ресурса: package root запущенного кода (параметр package_dir). */
    public const string RESOURCE_PACKAGE_DIR = 'package_dir';

    /** Дискриминатор ресурса: физический путь запущенного PHAR (параметр phar_path). */
    public const string RESOURCE_PHAR_PATH = 'phar_path';

    public function __construct(
        private readonly PharRuntimePathsProviderInterface $pathsProvider,
    ) {
    }

    /**
     * Маршрутизирует дискриминатор ресурса к провайдеру путей запущенного кода.
     * $getEnv не вызывается: имя в плейсхолдере — константа выбора ресурса,
     * а не env-переменная.
     */
    #[Override]
    public function getEnv(string $prefix, string $name, \Closure $getEnv): mixed
    {
        return match ($name) {
            self::RESOURCE_PACKAGE_DIR => $this->pathsProvider->packageDir(),
            self::RESOURCE_PHAR_PATH => $this->pathsProvider->runningPharPath(),
            default => throw new InvalidArgumentException(sprintf(
                'Неизвестный ресурс phar-runtime "%s" для процессора %s; ожидаются: %s, %s.',
                $name,
                self::ENV_PREFIX,
                self::RESOURCE_PACKAGE_DIR,
                self::RESOURCE_PHAR_PATH,
            )),
        };
    }

    /**
     * @return array<string, string> префикс процессора → PHP-тип результата
     */
    #[Override]
    public static function getProvidedTypes(): array
    {
        return [self::ENV_PREFIX => 'string'];
    }
}
