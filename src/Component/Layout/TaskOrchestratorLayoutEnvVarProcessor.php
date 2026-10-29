<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\Layout;

use InvalidArgumentException;
use Override;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * Env-процессор runtime layout-путей task-orchestrator (prefix `task_orchestrator_layout`).
 *
 * Разрешает layout-параметры (roles_dir, skills_dir, chains_yaml) в момент
 * обращения к параметру, а не при компиляции контейнера: выбор host/package
 * выполняется на каждом запуске через {@see HostPackageLayoutResolver}, поэтому
 * ресурсы host-проекта, созданные после первого запуска PHAR, доступны уже при
 * следующем запуске БЕЗ ручной очистки кеша (ранее выбор host/package
 * запекался в скомпилированный контейнер — дефект QA-3). Значение кешируется
 * только в пределах экземпляра контейнера (Container::getEnv) — штатное
 * поведение динамических параметров.
 *
 * Имя переменной в плейсхолдере (`%env(task_orchestrator_layout:roles_dir)%`
 * и т.п.) — ДИСКРИМИНАТОР ресурса layout, а НЕ переменная окружения: по
 * образцу встроенного Symfony-процессора `const` имя никогда не читается из
 * окружения, замыкание $getEnv не вызывается, задать значение через env нельзя.
 *
 * ❗ Контракт конструктора: ТОЛЬКО plain-параметры контейнера —
 * %task_orchestrator.base_path% (host-корень) и %task_orchestrator.package_dir%
 * (package-корень). Запрещено передавать в аргументы процессора env-плейсхолдеры
 * (%env(...)%): процессор создаётся контейнером при разрешении env-переменной,
 * поэтому %env()% в аргументах приводит к рекурсии Container::getEnv()
 * (ParameterCircularReferenceException). Правило распространяется и на будущие
 * зависимости процессора: их аргументы также обязаны быть plain-параметрами
 * либо сервисами без env-зависимостей. Ровно по этой причине резолвер
 * инстанциируется здесь из двух plain-строк, а не внедряется отдельным сервисом
 * с собственной конфигурацией.
 */
final class TaskOrchestratorLayoutEnvVarProcessor implements EnvVarProcessorInterface
{
    /** Prefix плейсхолдера `%env(task_orchestrator_layout:roles_dir)%` и др. */
    public const string ENV_PREFIX = 'task_orchestrator_layout';

    /** Дискриминатор ресурса: каталог role-файлов (параметр roles_dir). */
    public const string RESOURCE_ROLES_DIR = 'roles_dir';

    /** Дискриминатор ресурса: каталог skills (параметр skills_dir). */
    public const string RESOURCE_SKILLS_DIR = 'skills_dir';

    /** Дискриминатор ресурса: YAML-конфигурация цепочек (параметр chains_yaml). */
    public const string RESOURCE_CHAINS_YAML = 'chains_yaml';

    private readonly HostPackageLayoutResolver $resolver;

    /**
     * @param string $hostRoot    корень host-проекта (plain-параметр
     *                            %task_orchestrator.base_path%; см. контракт класса)
     * @param string $packageRoot package-корень (plain-параметр
     *                            %task_orchestrator.package_dir%)
     */
    public function __construct(
        string $hostRoot,
        string $packageRoot,
    ) {
        $this->resolver = new HostPackageLayoutResolver($hostRoot, $packageRoot);
    }

    /**
     * Маршрутизирует дискриминатор ресурса к чистому резолверу. $getEnv не
     * вызывается: имя в плейсхолдере — константа выбора ресурса, а не env-переменная.
     */
    #[Override]
    public function getEnv(string $prefix, string $name, \Closure $getEnv): mixed
    {
        return match ($name) {
            self::RESOURCE_ROLES_DIR => $this->resolver->resolveRolesDir(),
            self::RESOURCE_SKILLS_DIR => $this->resolver->resolveSkillsDir(),
            self::RESOURCE_CHAINS_YAML => $this->resolver->resolveChainsYaml(),
            default => throw new InvalidArgumentException(sprintf(
                'Неизвестный ресурс layout "%s" для процессора %s; ожидаются: %s, %s, %s.',
                $name,
                self::ENV_PREFIX,
                self::RESOURCE_ROLES_DIR,
                self::RESOURCE_SKILLS_DIR,
                self::RESOURCE_CHAINS_YAML,
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
