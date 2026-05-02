<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ChainDefinitionInterface;

/**
 * Контракт загрузки цепочек оркестрации.
 */
interface ChainLoaderInterface
{
    /**
     * Загружает цепочку по имени.
     *
     * @throws \TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\NotFoundExceptionInterface если цепочка не найдена
     */
    public function load(string $name): ChainDefinitionInterface;

    /**
     * Возвращает все доступные цепочки.
     *
     * @return array<string, ChainDefinitionInterface>
     */
    public function list(): array;

    /**
     * Переопределяет путь к источнику конфигурации и сбрасывает кэш.
     *
     * Предназначен для однократного вызова в CLI-контексте (опция --config).
     * Реализация по умолчанию — no-op, если источник нельзя переопределить.
     */
    public function overridePath(string $yamlPath): void;
}
