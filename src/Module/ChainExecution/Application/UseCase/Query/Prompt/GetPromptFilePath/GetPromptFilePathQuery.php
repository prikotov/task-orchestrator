<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Query\Prompt\GetPromptFilePath;

/**
 * Запрос пути к файлу описания роли.
 */
final readonly class GetPromptFilePathQuery
{
    public function __construct(public string $role) {}
}
