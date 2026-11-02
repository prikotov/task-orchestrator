<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\PharRuntime;

/**
 * Провайдер phar-зависимых путей запущенного кода (runtime).
 *
 * Единый источник значений для env-процессора
 * {@see TaskOrchestratorPharEnvVarProcessor} (prefix `task_orchestrator_phar`):
 * package root и физический путь PHAR разрешаются В МОМЕНТ обращения к
 * параметру, а не при компиляции контейнера — шаг (A) решения П2
 * (docs/research/phar-cache-roots-prevention.md, раздел 2.4). После
 * перемещения PHAR все потребители (layout-резолвер, привязка .phar-binding
 * команды agent:init) получают пути от ЗАПУЩЕННОГО архива — без очистки кеша
 * контейнера и без мёртвых phar://-ссылок при debug=false.
 *
 * Production-реализация — {@see \TaskOrchestrator\Common\Kernel} как
 * композиционный корень: packageDir() — location-based (каталог загруженного
 * кода), runningPharPath() — защищённый шов resolvePharPath() (Phar::running(false)).
 * Контейнер получает провайдер через alias интерфейса на сервис `kernel`
 * (config/services.yaml) — сервис без env-зависимостей, что отвечает контракту
 * аргументов env-процессоров (plain-параметры либо env-free сервисы).
 */
interface PharRuntimePathsProviderInterface
{
    /**
     * Package root: каталог пакета (src/, apps/, config/), где физически лежит
     * ЗАПУЩЕННЫЙ код. В обычной файловой системе — корень checkout'а; в PHAR —
     * phar://-корень запущенного архива (классы загружаются из архива, поэтому
     * перемещение PHAR автоматически меняет и этот путь).
     */
    public function packageDir(): string;

    /**
     * Физический путь запущенного PHAR; null — код исполняется не из архива.
     *
     * Единственный штатный источник в production — Phar::running(false): разбор
     * строки `phar://` недопустим (слэши/чувствительность к регистру хоста).
     */
    public function runningPharPath(): ?string;
}
