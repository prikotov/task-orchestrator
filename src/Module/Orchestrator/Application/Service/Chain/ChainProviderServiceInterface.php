<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigViolationDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainDefinitionDto;

/**
 * Application-интерфейс загрузки и валидации цепочек оркестрации.
 *
 * Инкапсулирует работу с Domain ChainLoaderInterface и ChainDefinitionValidator,
 * возвращая Application DTO вместо Domain VO.
 * Presentation-слой зависит только от этого интерфейса.
 */
interface ChainProviderServiceInterface
{
    /**
     * Загружает цепочку по имени.
     *
     * @throws \TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\NotFoundExceptionInterface если цепочка не найдена
     */
    public function load(string $name): ChainDefinitionDto;

    /**
     * Возвращает все доступные цепочки.
     *
     * @return array<string, ChainDefinitionDto>
     */
    public function list(): array;

    /**
     * Переопределяет путь к источнику конфигурации и сбрасывает кэш.
     *
     * Предназначен для однократного вызова в CLI-контексте (опция --config).
     */
    public function overridePath(string $yamlPath): void;

    /**
     * Валидирует определение цепочки и возвращает список нарушений.
     *
     * @param ChainDefinitionDto $chain определение цепочки
     *
     * @return list<ChainConfigViolationDto> пустой список = нарушений нет
     */
    public function validate(ChainDefinitionDto $chain): array;
}
