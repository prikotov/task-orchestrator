<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\CommandBus;

/**
 * Синхронная шина команд: единая точка доставки команд их Command Handler-ам.
 *
 * Contract (контракт) слоя Application: Command Handler вызывается только через
 * эту шину — прямой вызов через `__invoke()` запрещён конвенцией
 * `docs/conventions/layers/application/use-case.md` и проверяется PHPStan-правилом
 * `prikotov.useCase.directHandlerInvocation` пакета prikotov/coding-standard.
 *
 * Имя интерфейса следует конвенции command-handler.md
 * (`...\Component\CommandBus\CommandBusComponentInterface`): в этом репозитории
 * сквозные компоненты живут в `src/Component/`.
 */
interface CommandBusComponentInterface
{
    /**
     * Диспатчит команду её зарегистрированному Command Handler-у.
     *
     * Возвращаемое значение — результат хендлера: DTO результата (compute-контекст
     * без БД допускает возврат по конвенции use-case.md).
     *
     * @param object $command сообщение Command (Application\UseCase\Command\...)
     *
     * @return mixed результат выполнения команды (DTO/скаляр)
     *
     * @throws CommandBusException команда не зарегистрирована или хендлер нарушил контракт
     */
    public function execute(object $command): mixed;
}
