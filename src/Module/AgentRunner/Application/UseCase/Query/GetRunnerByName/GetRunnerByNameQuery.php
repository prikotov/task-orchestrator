<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunnerByName;

/**
 * DTO запроса runner'а по имени.
 *
 * Если $name === null — возвращается runner по умолчанию.
 */
final readonly class GetRunnerByNameQuery
{
    public function __construct(
        public ?string $name = null,
    ) {
    }
}
