<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Contract\Prompt;

/**
 * Контракт провайдера системных промптов для ролей AI-агентов.
 *
 * Расположен в Contract (а не Service), чтобы ServiceContractDependencyRule
 * не считал его cross-module сервисом при реализации в других модулях (Port/Adapter).
 */
interface PromptProviderInterface
{
    /**
     * Возвращает системный промпт для указанной роли.
     *
     * @throws \TaskOrchestrator\Common\Module\ChainExecution\Domain\Exception\NotFoundExceptionInterface если роль не найдена
     */
    public function getPrompt(string $role): string;

    /**
     * Возвращает абсолютный путь к файлу описания роли.
     *
     * @throws \TaskOrchestrator\Common\Module\ChainExecution\Domain\Exception\NotFoundExceptionInterface если роль не найдена
     */
    public function getPromptFilePath(string $role): string;

    /**
     * Проверяет существование роли.
     */
    public function roleExists(string $role): bool;

    /**
     * Возвращает список доступных ролей.
     *
     * @return array<string, string> name => description
     */
    public function getAvailableRoles(): array;
}
