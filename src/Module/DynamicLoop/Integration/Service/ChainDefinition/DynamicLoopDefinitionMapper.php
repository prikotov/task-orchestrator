<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Integration\Service\ChainDefinition;

use Override;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain\LoadRawChainQuery;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain\LoadRawChainQueryHandler;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\DynamicLoopConfigMapperInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Integration\ChainDefinitionProviderInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopPromptConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRoleConfigVo;

/**
 * Integration-сервис: загружает определения цепочек через ChainDefinition.Application
 * и транслирует ChainDefinition.Domain VO → DynamicLoop.Domain VO.
 *
 * ACL (Anti-Corruption Layer) на границе модулей.
 * Обращается к foreign Application (LoadRawChainQueryHandler), а не к foreign Domain.
 */
final readonly class DynamicLoopDefinitionMapper implements DynamicLoopConfigMapperInterface, ChainDefinitionProviderInterface
{
    public function __construct(
        private LoadRawChainQueryHandler $loadRawChainHandler,
    ) {
    }

    #[Override]
    public function loadDynamicChainConfig(string $chainName): DynamicLoopConfigVo
    {
        $chain = ($this->loadRawChainHandler)(new LoadRawChainQuery($chainName));
        assert($chain instanceof DynamicChainDefinitionVo);

        return $this->map($chain);
    }

    /**
     * Маппит DynamicChainDefinitionVo → DynamicLoopConfigVo.
     */
    #[Override]
    public function map(DynamicChainDefinitionVo $chain): DynamicLoopConfigVo
    {
        $shared = $chain->getSharedDefinition();
        $promptConfig = $chain->getPromptConfiguration();

        return new DynamicLoopConfigVo(
            name: $shared->getName(),
            description: $shared->getDescription(),
            budget: $this->mapBudget($shared->getBudget()),
            timeout: $shared->getTimeout(),
            maxTime: $shared->getMaxTime(),
            roleConfigs: $this->mapRoleConfigs($shared->getRoles()),
            facilitator: $chain->getFacilitator(),
            participants: $chain->getParticipants(),
            maxRounds: $chain->getMaxRounds(),
            promptConfiguration: new DynamicLoopPromptConfigVo(
                brainstormSystemPrompt: $promptConfig->getBrainstormSystemPrompt(),
                facilitatorAppendPrompt: $promptConfig->getFacilitatorAppendPrompt(),
                facilitatorStartPrompt: $promptConfig->getFacilitatorStartPrompt(),
                facilitatorContinuePrompt: $promptConfig->getFacilitatorContinuePrompt(),
                facilitatorFinalizePrompt: $promptConfig->getFacilitatorFinalizePrompt(),
                participantAppendPrompt: $promptConfig->getParticipantAppendPrompt(),
                participantUserPrompt: $promptConfig->getParticipantUserPrompt(),
            ),
            defaultRetryPolicy: null,
        );
    }

    private function mapBudget(?BudgetVo $budget): ?DynamicLoopBudgetVo
    {
        if ($budget === null) {
            return null;
        }

        $perRoleBudgets = [];
        foreach ($budget->getPerRoleBudgets() as $role => $roleBudget) {
            $mapped = $this->mapBudget($roleBudget);
            if ($mapped !== null) {
                $perRoleBudgets[$role] = $mapped;
            }
        }

        return new DynamicLoopBudgetVo(
            maxCostTotal: $budget->getMaxCostTotal(),
            maxCostPerStep: $budget->getMaxCostPerStep(),
            perRoleBudgets: $perRoleBudgets,
        );
    }

    /**
     * @param array<string, RoleConfigVo> $roles
     *
     * @return array<string, DynamicLoopRoleConfigVo>
     */
    private function mapRoleConfigs(array $roles): array
    {
        $result = [];
        foreach ($roles as $role => $config) {
            $result[$role] = new DynamicLoopRoleConfigVo(
                command: $config->getCommand(),
                timeout: $config->getTimeout(),
                promptFile: $config->getPromptFile(),
            );
        }

        return $result;
    }
}
