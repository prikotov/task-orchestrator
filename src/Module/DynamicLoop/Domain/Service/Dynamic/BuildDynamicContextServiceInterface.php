<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;

/**
 * Создание DynamicLoopContextVo из DynamicLoopConfigVo и параметров запуска.
 */
interface BuildDynamicContextServiceInterface
{
    /**
     * @param list<string> $participants
     */
    public function buildContext(
        DynamicLoopConfigVo $chain,
        string $facilitatorRole,
        array $participants,
        int $maxRounds,
        string $topic,
        ?string $workingDir,
        int $timeout,
        ?int $maxTime = null,
    ): DynamicLoopContextVo;

    /**
     * @param list<string> $effectiveParticipants
     *
     * @return array<string, mixed>
     */
    public function buildInvocation(
        DynamicLoopConfigVo $chain,
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
