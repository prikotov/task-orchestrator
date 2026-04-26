<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainDefinitionDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainStepDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;

/**
 * Маппинг Domain ChainDefinitionVo → Application ChainDefinitionDto.
 */
final readonly class ChainDefinitionDtoMapper
{
    public function map(ChainDefinitionVo $chain): ChainDefinitionDto
    {
        $steps = [];
        foreach ($chain->getSteps() as $step) {
            $roleConfig = $step->getRole() !== null ? $chain->getRoleConfig($step->getRole()) : null;
            $fallbackRunner = $roleConfig?->getFallback()?->getRunnerName();

            $steps[] = new ChainStepDto(
                role: $step->getRole(),
                runner: $step->getRunner(),
                label: $step->getLabel(),
                isQualityGate: $step->isQualityGate(),
                fallbackRunnerName: $fallbackRunner,
            );
        }

        return new ChainDefinitionDto(
            name: $chain->getName(),
            isDynamic: $chain->isDynamic(),
            facilitator: $chain->getFacilitator(),
            participants: $chain->getParticipants(),
            maxRounds: $chain->getMaxRounds(),
            steps: $steps,
        );
    }

    /**
     * @param array<string, ChainDefinitionVo> $chains
     * @return array<string, ChainDefinitionDto>
     */
    public function mapList(array $chains): array
    {
        $result = [];
        foreach ($chains as $name => $chain) {
            $result[$name] = $this->map($chain);
        }

        return $result;
    }
}
