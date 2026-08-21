<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\QueryBus;

/**
 * Синхронная шина запросов: единая точка доставки запросов их Query Handler-ам.
 *
 * Contract (контракт) слоя Application: Query Handler вызывается только через
 * эту шину — прямой вызов через `__invoke()` запрещён конвенцией
 * `docs/conventions/layers/application/use-case.md` и проверяется PHPStan-правилом
 * `prikotov.useCase.directHandlerInvocation` пакета prikotov/coding-standard.
 *
 * Имя интерфейса следует конвенции command-handler.md
 * (`...\Component\QueryBus\QueryBusComponentInterface`): в этом репозитории
 * сквозные компоненты живут в `src/Component/`.
 */
interface QueryBusComponentInterface
{
    /**
     * Диспатчит запрос его зарегистрированному Query Handler-у.
     *
     * @param object $query сообщение Query (Application\UseCase\Query\...)
     *
     * @return mixed результат запроса (DTO/скаляр)
     *
     * @throws QueryBusException запрос не зарегистрирован или хендлер нарушил контракт
     */
    public function query(object $query): mixed;
}
