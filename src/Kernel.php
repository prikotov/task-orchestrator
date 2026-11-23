<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use TaskOrchestrator\Common\Component\CommandBus\DependencyInjection\UseCaseBusCompilerPass;
use TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ConsoleServicesRegistrar;
use TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\KernelParametersRegistrar;
use TaskOrchestrator\Common\Component\ModuleSystem\ModuleKernelTrait;
use TaskOrchestrator\Common\Component\PharCache\CacheRootGarbageCollector;
use TaskOrchestrator\Common\Component\PharCache\CacheRootOwner;
use TaskOrchestrator\Common\Component\PharCache\CacheRootOwnershipLock;
use TaskOrchestrator\Common\Component\PharRuntime\PharRunningPathResolver;
use TaskOrchestrator\Common\Component\PharRuntime\PharRuntimePathsProviderInterface;
use TaskOrchestrator\Common\Component\Release\ReleaseVersionResolver;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Contract\Chain\ExecutionStrategyInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ExecuteStepServiceInterface;
use Throwable;
use const PHP_OS_FAMILY;

/**
 * Ядро приложения TaskOrchestrator (console-only).
 *
 * Заменяет прежнюю ручную сборку контейнера в bin/*. Следует модульной
 * архитектуре: MicroKernelTrait загружает config/packages/*, config/services.yaml
 * (который, в свою очередь, импортирует config/console_services.yaml) и
 * config/bundles.php; ModuleKernelTrait регистрирует модули из config/modules.php
 * (подгрузка Resource/config/services.yaml, Twig- и Translation-путей).
 *
 * Package root (каталог пакета с src/, apps/, config/) разрешается через
 * {@see packageDir()} = dirname(__DIR__): CWD-независимо и одинаково в
 * обычной FS и в PHAR (где наследуемый BaseKernel::getProjectDir() даёт
 * неверный phar://.../src, так как composer.json не упакован в PHAR).
 * packageDir() используется для config/modules.php, параметра
 * task_orchestrator.package_dir (через env-процессор Component/PharRuntime,
 * см. ниже) и базовых путей PHAR-safe регистрации.
 *
 * {@see getProjectDir()} переопределён: в PHAR возвращает package root
 * (через packageDir()), в обычной FS — наследуемый поиск по composer.json.
 * Это чинит Symfony-конфигурацию в PHAR (config/bundles.php, config/packages/*,
 * config/services.yaml грузятся через MicroKernelTrait::getConfigDir() =
 * getProjectDir()/config) и параметр kernel.project_dir.
 *
 * Параметры task_orchestrator.* собирает KernelParametersRegistrar, устанавливаются
 * они в getKernelParameters() — самый ранний этап сборки контейнера, поэтому
 * параметры гарантированно доступны при импорте services.yaml (resource/exclude
 * и аргументы ссылаются на них).
 *
 * Dual-context (vendor binary): конструктор принимает $projectRoot — каталог
 * host-проекта, из которого берутся роли, цепочки и base_path (с fallback на
 * package root, если в host-проекте ресурсов нет). В standalone-режиме
 * $projectRoot не передаётся и совпадает с package root. host-ресурсы
 * разрешаются через {@see getProjectRoot()}, package-ресурсы — через
 * packageDir()/getProjectDir(). Layout-пути (roles_dir/skills_dir/chains_yaml)
 * разрешаются во время выполнения (runtime) env-процессором Component/Layout
 * ({@see \TaskOrchestrator\Common\Component\Layout\TaskOrchestratorLayoutEnvVarProcessor}):
 * они НЕ запекаются в скомпилированный контейнер, поэтому ресурсы host-проекта,
 * созданные после первого запуска, видны при следующем запуске без очистки кеша.
 * Phar-зависимые package_dir/phar_path разрешаются так же в runtime —
 * env-процессором Component/PharRuntime
 * ({@see \TaskOrchestrator\Common\Component\PharRuntime\TaskOrchestratorPharEnvVarProcessor},
 * шаг (A) решения П2): после перемещения PHAR все потребители получают значения
 * от запущенного архива, даже если персистентный кеш контейнера переиспользуется
 * в debug=false (ConfigCache не проверяет свежесть). Ядро выступает провайдером
 * путей ({@see PharRuntimePathsProviderInterface}): packageDir() — location-based,
 * runningPharPath() — защищённый шов resolvePharPath().
 *
 * Phar: package root доступен только для чтения, поэтому cache/log при работе
 * из Phar уходят в системный временный каталог, изолированный по паре
 * (host-проект, физический путь PHAR) — см. writableRoot(); упрощение ключа
 * изоляции — отдельный шаг (B) решения П2, здесь он не выполняется.
 *
 * Жизненный цикл кеш-корня PHAR (FR-4): в PHAR-режиме до parent::boot() ядро
 * захватывает свой корень во владение (mkdir `0700` → flock(LOCK_EX|LOCK_NB) →
 * маркер владения со self-hash; {@see CacheRootOwner}) и удерживает дескриптор
 * блокировки до завершения процесса. Затем выполняется ограниченная очистка
 * устаревших корней прежних расположений ({@see CacheRootGarbageCollector}):
 * удаляются только корни с валидным маркером (self-hash ↔ имя каталога),
 * свободным flock и совпадающим владельцем (fileowner === posix_getuid);
 * текущий, активный, неизвестный (без маркера, в том числе legacy) и симлинк
 * узлы сохраняются. Вне PHAR захват и очистка — no-op. Сбой очистки —
 * предупреждение в STDERR, не меняющее exit code команды (warning-семантика
 * контракта; fail-fast отклонён: он маскирует результат команды).
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Kernel extends BaseKernel implements PharRuntimePathsProviderInterface
{
    use MicroKernelTrait;
    use ModuleKernelTrait;

    /**
     * @param string      $environment имя окружения (prod/dev/test)
     * @param bool        $debug       режим отладки
     * @param string|null $projectRoot каталог host-проекта для ролей/цепочек/base_path;
     *                                 null = standalone (совпадает с package root)
     * @param string      $platformFamily явно внедрённое семейство ОС для platform-specific компонентов
     */
    public function __construct(
        string $environment,
        bool $debug,
        private readonly ?string $projectRoot = null,
        private readonly string $platformFamily = PHP_OS_FAMILY,
    ) {
        parent::__construct($environment, $debug);
    }

    /**
     * Дескриптор блокировки владения собственным кеш-корнем PHAR. Удерживается
     * до завершения процесса (см. {@see boot()}); null — вне PHAR либо захват
     * не состоялся (активный чужой корень / незащищённые права).
     */
    private ?CacheRootOwnershipLock $pharCacheRootLock = null;

    /**
     * Загрузка ядра: в PHAR-режиме перед сборкой контейнера выполняется
     * захват кеш-корня во владение (mkdir → flock → маркер, ДО parent::boot())
     * и очистка устаревших корней прежних расположений. Вне PHAR — no-op.
     *
     * Дескриптор блокировки собственного корня (см. $pharCacheRootLock)
     * удерживается до завершения процесса: это арбитр активности для параллельных
     * запусков и защиты корня от чужой очистки. При аварийном завершении блокировка
     * освобождается ОС; shutdown() блокировку НЕ снимает (сознательно: ядро может
     * перезапускаться в рамках процесса — повторный boot сохраняет владение).
     */
    #[Override]
    public function boot(): void
    {
        if (!$this->booted) {
            $this->claimPharCacheRoot();
            $this->collectStalePharCacheRoots();
        }

        parent::boot();
    }

    /**
     * Каталог host-проекта: источник ролей, цепочек и base_path.
     * В standalone-режиме совпадает с package root (getProjectDir()).
     */
    public function getProjectRoot(): string
    {
        return $this->projectRoot ?? $this->getProjectDir();
    }

    /**
     * Корень проекта для Symfony: каталог, где лежат config/ (bundles.php,
     * packages/*, services.yaml), src/, templates/ и (в обычной FS) composer.json.
     *
     * В обычной FS наследуемый BaseKernel::getProjectDir() находит packageRoot
     * по composer.json. В PHAR root composer.json не упакован, поэтому
     * наследуемый поиск падает на dirname(__FILE__) = phar://.../src —
     * неверный проектный корень, из-за которого не грузятся config/services.yaml,
     * config/packages/*, config/bundles.php (через MicroKernelTrait::getConfigDir()
     * = getProjectDir()/config) и ломается весь PHAR-контейнер.
     *
     * Поэтому в PHAR явно возвращаем package root ({@see packageDir()}): там
     * лежат config/, src/, templates/, упакованные в архив. В обычной FS
     * поведение наследуемого getProjectDir() сохраняется.
     *
     * НЕ путать с {@see getProjectRoot()} — каталогом host-проекта для ролей,
     * цепочек, base_path и writable cache/log.
     */
    #[Override]
    public function getProjectDir(): string
    {
        return $this->isPhar() ? $this->packageDir() : parent::getProjectDir();
    }

    /**
     * Package root: каталог пакета, где лежат src/, apps/, config/. Реализация
     * {@see PharRuntimePathsProviderInterface::packageDir()}.
     *
     * CWD-независимо и работает как в обычной файловой системе, так и в PHAR.
     * В PHAR composer.json не упакован, поэтому наследуемый
     * BaseKernel::getProjectDir() даёт неверный phar://.../src: он ищет
     * composer.json, поднимаясь от Kernel.php, и при отсутствии падает на
     * dirname(__FILE__). Kernel.php лежит в src/, поэтому package root =
     * dirname(__DIR__).
     *
     * ВНИМАНИЕ: в PHAR это runtime-значение запущенного кода (phar://-корень
     * запущенного архива). Как параметр контейнера
     * task_orchestrator.package_dir оно отдаётся через env-процессор
     * Component/PharRuntime и НЕ замораживается при компиляции; сам метод
     * используется и на этапе компиляции (config/modules.php, PHAR-safe
     * регистрация) — там загруженный код совпадает с запущенным.
     *
     * НЕ путать с {@see getProjectRoot()} — каталогом host-проекта для ролей,
     * цепочек, base_path и writable cache/log.
     *
     *   dev:  dirname('/path/src')          = '/path'           (root проекта).
     *   PHAR: dirname('phar://x.phar/src')  = 'phar://x.phar'   (root PHAR).
     */
    #[Override]
    public function packageDir(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Физический путь запущенного PHAR или null вне PHAR. Реализация
     * {@see PharRuntimePathsProviderInterface::runningPharPath()}.
     *
     * Runtime-источник env-процессора Component/PharRuntime: параметры
     * task_orchestrator.package_dir/phar_path разрешаются от запущенного
     * архива при каждом обращении (шаг (A) решения П2) — в том числе на
     * переиспользованном персистентном кеше контейнера. Делегирует защищённому
     * шву {@see resolvePharPath()} (инъекция пути в тестах ядра вне реального
     * архива).
     */
    #[Override]
    public function runningPharPath(): ?string
    {
        return $this->resolvePharPath();
    }

    /**
     * Изолирует контейнер CLI от кеша host-приложения и предыдущих релизов.
     *
     * Composer-host сохраняет writable-артефакты в своём проекте, но общий
     * `<host>/var/cache/<env>` небезопасен: там может лежать контейнер другого
     * Kernel или предыдущей версии task-orchestrator. Версия входит в путь,
     * поэтому обновлённый пакет компилирует совместимый контейнер автоматически.
     * В Phar к корню добавляется изоляция по паре (host-проект, физический
     * путь архива) ({@see writableRoot()}) — действующий контракт изоляции
     * кеш-корней; phar-зависимые параметры ядра при этом разрешаются в runtime
     * (env-процессор Component/PharRuntime), поэтому даже переиспользованный
     * контейнер ссылается на запущенный архив. Явный APP_CACHE_DIR остаётся
     * полным пользовательским переопределением.
     */
    #[Override]
    public function getCacheDir(): string
    {
        $base = $_SERVER['APP_CACHE_DIR'] ?? null;
        if (is_string($base) && $base !== '') {
            return $base . '/' . $this->environment;
        }

        return $this->writableRoot()
            . '/var/cache/task-orchestrator/'
            . $this->resolveVersion()
            . '/'
            . $this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        $base = $_SERVER['APP_LOG_DIR'] ?? null;
        if (is_string($base) && $base !== '') {
            return $base;
        }

        return $this->writableRoot() . '/var/log';
    }

    /**
     * Параметры ядра: стандартные kernel.* + параметры TaskOrchestrator.
     *
     * Состав и семантика task_orchestrator.* — в
     * {@see KernelParametersRegistrar::parameters()}: устанавливаются на этапе
     * конструирования контейнера (раньше любого импорта конфигурации), поэтому
     * доступны services.yaml и модульным Resource/config/services.yaml без
     * отдельной настройки. Объединение через `+` безопасно: ключи kernel.* и
     * task_orchestrator.* не пересекаются, приоритет у стандартных kernel.*.
     *
     * @return array<string, mixed>
     */
    #[Override]
    protected function getKernelParameters(): array
    {
        return parent::getKernelParameters() + (new KernelParametersRegistrar(
            projectRoot: $this->getProjectRoot(),
            isPhar: $this->isPhar(),
            platformFamily: $this->platformFamily,
            version: $this->resolveVersion(),
        ))->parameters();
    }

    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        $this->registerAutoconfiguration($container);
        $this->registerModules($container, $this->getModules());

        // PHAR-safe регистрация сервисов Presentation-слоя apps/console:
        // механика каталогов/неймспейсов — в ConsoleServicesRegistrar (см. его
        // PHPDoc про порядок относительно console_services.yaml и теги Command).
        (new ConsoleServicesRegistrar($this->packageDir()))->register($container);

        // Шины Use Case: пас добавляется ПОСЛЕ регистрационных пасов модулей,
        // поэтому в process() видны все уже зарегистрированные хендлеры
        // (и из module-local services.yaml, и из PHAR-safe регистратора).
        $container->addCompilerPass(new UseCaseBusCompilerPass());
    }

    /**
     * Container-wide autoconfiguration для тегов, которые раньше объявлялись
     * через module-local _instanceof. После перехода с resource: на PHAR-safe
     * регистратор (ModuleServiceRegistrar) _instanceof больше не применяется
     * (оно работало только к классам из resource: своего файла).
     *
     * Container-wide autoconfig применяется ко всем сервисам с autoconfigured=true,
     * независимо от способа регистрации (resource: или регистратор) — единообразно
     * в dev и PHAR.
     */
    private function registerAutoconfiguration(ContainerBuilder $container): void
    {
        // Теги интерфейсов регистрируются container-wide (а не module-local
        // _instanceof), поэтому применяются ко всем autoconfigured-сервисам
        // независимо от способа регистрации (explicit def или PHAR-safe регистратор).
        $container->registerForAutoconfiguration(
            AgentRunnerInterface::class,
        )->addTag('agent.runner');

        $container->registerForAutoconfiguration(
            ExecutionStrategyInterface::class,
        )->addTag('orchestrator.execution_strategy');

        $container->registerForAutoconfiguration(
            ExecuteStepServiceInterface::class,
        )->addTag('chain_execution.step_runner');
    }

    /**
     * Реестр модулей из config/modules.php (относительно package root).
     *
     * @return array<string, array<string, bool>>
     */
    private function getModules(): array
    {
        $file = $this->packageDir() . '/config/modules.php';

        return is_file($file) ? require $file : [];
    }

    /**
     * Действующая версия релиза: приоритет кандидатов и валидация SemVer —
     * в {@see ReleaseVersionResolver} и {@see ReleaseVersionVo} (явный
     * APP_RELEASE_VERSION → pretty-версия пакета → pretty-версия корневого
     * пакета). Хелпер точки сборки создаётся через `new`: вызывается до/вне
     * контейнера при сборке параметров ядра и пути кеш-корня.
     */
    private function resolveVersion(): string
    {
        return (new ReleaseVersionResolver())->resolve();
    }

    /**
     * Корень для writable-артефактов (cache/log).
     *
     * Из Phar package root доступен только для чтения — используем системный
     * временный каталог, изолированный по паре (host-проект, физический путь
     * PHAR). Физический путь архива входит в ключ изоляции по действующему
     * контракту «пары»: phar-зависимые параметры task_orchestrator.package_dir/
     * phar_path больше НЕ замораживаются (шаг (A) решения П2 — runtime-разрешение
     * через env-процессор Component/PharRuntime), но изоляция по паре сохраняется
     * и дополнительно даёт свежую компиляцию после перемещения архива — контракт
     * повторной установки agent:init --force из нового расположения. Упрощение
     * ключа (только host-проект) — отдельный шаг (B) решения П2, здесь не
     * выполняется. В обычном режиме — host-проект (projectRoot),
     * что даёт изоляцию кеша по CWD и согласовано с другими writable-путями
     * (var/sessions, var/cache/git-identity).
     */
    private function writableRoot(): string
    {
        $pharPath = $this->resolvePharPath();

        if ($pharPath === null) {
            return $this->getProjectRoot();
        }

        return $this->pharCacheBaseDir() . '/' . $this->pharCacheRootName();
    }

    /**
     * Базовый каталог кеш-корней PHAR: `<sys_tmp>/task-orchestrator`.
     *
     * Protected — проверяемый шов для перенаправления базового каталога
     * кеш-корней в изолированный временный каталог в тестах ядра: интеграционные
     * тесты жизненного цикла ({@see CacheRootGarbageCollector}) не должны
     * выполнять очистку в реальном системном temp (там лежат корни действующих
     * PHAR-запусков) — по образцу resolvePharPath().
     */
    protected function pharCacheBaseDir(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . '/task-orchestrator';
    }

    /**
     * Self-hash собственного кеш-корня: хеш пары (host-проект, физический путь
     * PHAR) — совпадает с именем каталога корня ({@see writableRoot()}) и со
     * значением в маркере владения.
     */
    private function pharCacheRootName(): string
    {
        return hash('xxh64', $this->getProjectRoot() . "\0" . (string) $this->resolvePharPath());
    }

    /**
     * Захват собственного кеш-корня во владение (только PHAR; вне PHAR — no-op):
     * mkdir `0700` → flock(LOCK_EX|LOCK_NB) → маркер владения. Неудачный захват
     * (активный чужой корень, незащищённые права) не прерывает загрузку: ядро
     * продолжает работу без признака владения.
     */
    private function claimPharCacheRoot(): void
    {
        if ($this->pharCacheRootLock !== null || $this->resolvePharPath() === null) {
            return;
        }

        $this->pharCacheRootLock = (new CacheRootOwner())->claim(
            $this->pharCacheBaseDir(),
            $this->pharCacheRootName(),
        );
    }

    /**
     * Очистка устаревших кеш-корней (только PHAR; вне PHAR — no-op).
     *
     * Warning-семантика контракта FR-4: любой сбой очистки не прерывает загрузку
     * и не меняет exit code команды — в STDERR выдаётся безопасная диагностика
     * (только счётчики, без путей и содержимого кеша).
     */
    private function collectStalePharCacheRoots(): void
    {
        if ($this->resolvePharPath() === null) {
            return;
        }

        try {
            $report = (new CacheRootGarbageCollector())->collect(
                $this->pharCacheBaseDir(),
                $this->pharCacheRootName(),
            );

            if ($report->hasFailures()) {
                $this->reportCacheRootCleanupFailure(
                    'task-orchestrator: не удалось полностью очистить устаревшие кеш-каталоги (приложение работает; '
                    . $report->toSafeSummary()
                    . '); повторная попытка — при следующем запуске.',
                );
            }
        } catch (Throwable) {
            $this->reportCacheRootCleanupFailure(
                'task-orchestrator: очистка устаревших кеш-каталогов прервана ошибкой '
                . '(приложение работает); повторная попытка — при следующем запуске.',
            );
        }
    }

    /**
     * Выдаёт предупреждение о сбое очистки в STDERR (без путей и содержимого
     * кеша). Protected — проверяемый шов для перехвата диагностики в тестах
     * (по образцу resolvePharPath() в Kernel).
     */
    protected function reportCacheRootCleanupFailure(string $message): void
    {
        // defined(): константа STDERR объявлена только в CLI SAPI (в web-контексте
        // и в части тестовых прогонов её нет); для Psalm STDERR — всегда resource.
        if (defined('STDERR')) {
            fwrite(STDERR, $message . PHP_EOL);
        }
    }

    private function isPhar(): bool
    {
        return str_starts_with(__FILE__, 'phar://');
    }

    /**
     * Физический путь запущенного PHAR или null вне PHAR.
     *
     * Единственный штатный источник физического пути архива — Phar::running(false):
     * разбор строки `phar://` недопустим (слэши/чувствительность к регистру хоста).
     * Единый защищённый шов пути архива: публичный {@see runningPharPath()}
     * (провайдер Component/PharRuntime — runtime env-параметры
     * task_orchestrator.package_dir/phar_path и привязка .phar-binding
     * agent:init) и ключ изоляции writable-корня ({@see writableRoot()}).
     *
     * Protected — проверяемый шов для инъекции физического пути PHAR в тестах
     * ядра вне реального архива (по образцу renamePath() в
     * InstallBecomeRoleService).
     */
    protected function resolvePharPath(): ?string
    {
        // static API \Phar изолирован в PharRunningPathResolver (без StaticAccess
        // в Kernel; см. его PHPDoc) — поведение идентично прежнему inline-коду.
        return (new PharRunningPathResolver())->runningPath();
    }
}
