<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken;

/**
 * Вход UseCase получения installation token.
 *
 * Несёт единственное поле — repository slug в формате `<owner>/<repo>`.
 * Парсинг и валидация slug выполняется внутри handler (Domain VO).
 */
final readonly class ObtainTokenCommand
{
    public function __construct(public string $repoSlug)
    {
    }
}
