<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;

/**
 * Создание DynamicChainContextVo из ChainDefinitionVo и параметров запуска.
 */
interface BuildDynamicContextServiceInterface
{
    /**
     * Собирает DTO контекста dynamic-цепочки.
     *
     * @param list<string> $participants
     */
    public function buildContext(
        ChainDefinitionVo $chain,
        string $facilitatorRole,
        array $participants,
        int $maxRounds,
        string $topic,
        ?string $workingDir,
        int $timeout,
        ?int $maxTime = null,
    ): DynamicChainContextVo;

    /**
     * Формирует invocation-массив для записи в session.json.
     *
     * @param list<string> $effectiveParticipants
     *
     * @return array<string, mixed>
     */
    public function buildInvocation(
        ChainDefinitionVo $chain,
        string $task,
        int $timeout,
        ?string $workingDir,
        ?string $resumeDir,
        string $effectiveFacilitator,
        array $effectiveParticipants,
        int $effectiveMaxRounds,
        string $effectiveTopic,
    ): array;
}
