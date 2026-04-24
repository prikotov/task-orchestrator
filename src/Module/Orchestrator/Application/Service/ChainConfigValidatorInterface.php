<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigValidationResultDto;

/**
 * Сервис валидации конфигурации цепочек оркестрации.
 *
 * Application-сервис: проверяет chains.yaml на структурную корректность,
 * наличие обязательных полей и правильность типов — без запуска оркестрации.
 * Presentation-слой вызывает этот интерфейс, а не Domain-исключения напрямую.
 */
interface ChainConfigValidatorInterface
{
    /**
     * Валидирует все цепочки в конфигурации.
     */
    public function validateAll(): ChainConfigValidationResultDto;

    /**
     * Валидирует конкретную цепочку по имени.
     *
     * Если цепочка не найдена — возвращает ошибку в результате.
     */
    public function validateChain(string $chainName): ChainConfigValidationResultDto;
}
