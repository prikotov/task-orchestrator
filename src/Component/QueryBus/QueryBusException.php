<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\QueryBus;

/**
 * Ошибка диспетчеризации запроса: запрос не зарегистрирован
 * или Query Handler нарушил контракт вызова.
 */
final class QueryBusException extends \RuntimeException
{
}
