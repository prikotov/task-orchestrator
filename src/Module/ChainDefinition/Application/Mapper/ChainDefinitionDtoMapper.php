<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\Mapper;

use TaskOrchestrator\Common\Module\ChainDefinition\Application\Dto\ChainDefinitionDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Dto\ChainStepDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionalChainDefinitionVo;

/**
 * Маппинг Domain ChainDefinitionInterface → Application ChainDefinitionDto.
 */
final readonly class ChainDefinitionDtoMapper
{
    public function map(ChainDefinitionInterface $chain): ChainDefinitionDto
    {
        $steps = [];
        $facilitator = null;
        $participants = [];
        $maxRounds = 0;

        if ($chain instanceof StaticChainDefinitionVo || $chain instanceof ConditionalChainDefinitionVo) {
            foreach ($chain->getSteps() as $step) {
                $roleConfig = $step->getRole() !== null ? $chain->getSharedDefinition()->getRoleConfig($step->getRole()) : null;
                $fallbackRunner = $roleConfig?->getFallback()?->getRunnerName();

                $steps[] = new ChainStepDto(
                    role: $step->getRole(),
                    runner: $step->getRunner(),
                    label: $step->getLabel(),
                    isQualityGate: $step->isQualityGate(),
                    fallbackRunnerName: $fallbackRunner,
                );
            }
        }

        if ($chain instanceof DynamicChainDefinitionVo) {
            $facilitator = $chain->getFacilitator();
            $participants = $chain->getParticipants();
            $maxRounds = $chain->getMaxRounds();
        }

        return new ChainDefinitionDto(
            name: $chain->getName(),
            isDynamic: $chain->getSharedDefinition()->isDynamic(),
            facilitator: $facilitator,
            participants: $participants,
            maxRounds: $maxRounds,
            steps: $steps,
        );
    }

    /**
     * @param array<string, ChainDefinitionInterface> $chains
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
