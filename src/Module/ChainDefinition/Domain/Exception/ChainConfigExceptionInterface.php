<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception;

/**
 * Маркерный интерфейс исключений конфигурации цепочек в модуле ChainDefinition.
 *
 * Позволяет Application-слою единообразно ловить ошибки конфигурации цепочек
 * (выбрасываемые при load/валидации), не привязываясь к конкретному классу-носителю.
 * Паттерн маркерного интерфейса — как {@see NotFoundExceptionInterface}.
 *
 * «Выбрасываем реализацию, ловим интерфейс» (см. docs/conventions/core_patterns/exception.md).
 */
interface ChainConfigExceptionInterface extends \Throwable
{
}
