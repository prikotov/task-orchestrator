<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Exception;





/**
 * Выбрасывается, когда аргумент похож на путь к файлу роли, но файл не найден
 * ни по одному из базисов резолвинга.
 */
final class RoleArgumentNotFoundException extends AgentRoleException implements NotFoundExceptionInterface
{
    /**
     * @param list<string> $bases базисы (абсолютные каталоги), от которых выполнялся поиск
     */
    public function __construct(string $argument, array $bases)
    {
        parent::__construct(sprintf(
            "Файл роли не найден: %s.\n"
            . "Искал от базисов: %s.\n"
            . 'Передайте имя роли (snake_case, например team_lead_alex) или существующий '
            . 'путь к файлу роли — абсолютный или от корня проекта.',
            $argument,
            $bases === [] ? '(базисы не заданы)' : implode('; ', $bases),
        ));
    }
}
