<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\Prompt\GetPromptFilePath;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Prompt\PromptProviderInterface;

/**
 * Возвращает путь к файлу описания роли.
 *
 * Application-level API для кросс-модульного доступа:
 * Integration-слой другого модуля вызывает этот QueryHandler
 * (Integration → foreign Application — разрешено Deptrac).
 */
class GetPromptFilePathQueryHandler
{
    public function __construct(
        private PromptProviderInterface $promptProvider,
    ) {
    }

    public function __invoke(GetPromptFilePathQuery $query): string
    {
        return $this->promptProvider->getPromptFilePath($query->role);
    }
}
