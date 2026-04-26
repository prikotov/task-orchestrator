<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainConfigViolationDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainDefinitionDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainStepDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\ChainDefinitionValidator;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainConfigViolationVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;

/**
 * Application-сервис: загрузка и валидация цепочек оркестрации.
 *
 * Делегирует загрузку к Domain ChainLoaderInterface и валидацию к Domain ChainDefinitionValidator,
 * маппит Domain VO в Application DTO. Presentation-слой зависит только от интерфейса.
 */
final readonly class ChainProviderService implements ChainProviderServiceInterface
{
    public function __construct(
        private ChainLoaderInterface $chainLoader,
        private ChainDefinitionValidator $chainValidator,
    ) {
    }

    #[Override]
    public function load(string $name): ChainDefinitionDto
    {
        return $this->mapToDto($this->chainLoader->load($name));
    }

    #[Override]
    public function list(): array
    {
        $result = [];
        foreach ($this->chainLoader->list() as $name => $chain) {
            $result[$name] = $this->mapToDto($chain);
        }

        return $result;
    }

    #[Override]
    public function overridePath(string $yamlPath): void
    {
        $this->chainLoader->overridePath($yamlPath);
    }

    #[Override]
    public function validate(ChainDefinitionDto $chain): array
    {
        // Domain-валидатор работает с VO. DTO не содержит всех полей VO (промпты, fixIterations),
        // поэтому загружаем VO заново по имени. Для CLI-контекста парсинг YAML мгновенный.
        $chainVo = $this->chainLoader->load($chain->name);
        $violations = $this->chainValidator->validate($chainVo);

        return array_map(
            static fn(ChainConfigViolationVo $v): ChainConfigViolationDto => new ChainConfigViolationDto(
                chainName: $v->getChainName(),
                field: $v->getField(),
                message: $v->getMessage(),
            ),
            $violations,
        );
    }

    private function mapToDto(ChainDefinitionVo $chain): ChainDefinitionDto
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
}
