<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\CreateRetryableRunner;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;

/**
 * DTO результата создания retryable runner'а.
 *
 * Содержит обёрнутый AgentRunnerInterface — это доменный интерфейс,
 * но он возвращается как часть Application-контракта для последующего
 * использования внутри модуля.
 */
final readonly class CreateRetryableRunnerResultDto
{
    public function __construct(
        public AgentRunnerInterface $runner,
    ) {
    }
}
