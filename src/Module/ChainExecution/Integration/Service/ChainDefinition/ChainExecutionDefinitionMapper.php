<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\ChainDefinition;

use Override;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionalChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FallbackConfigVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ConditionOperatorEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Integration\ChainDefinitionProviderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ConditionExpressionVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionBudgetVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionConditionalChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionFallbackConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionFixIterationGroupVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRoleConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStaticChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;

/**
 * Integration-маппер: ChainDefinition.Domain VO → ChainExecution.Domain VO.
 *
 * Транслирует StaticChainDefinitionVo / ConditionalChainDefinitionVo
 * в Execution-аналоги, разрывая зависимость ChainExecution.Domain от ChainDefinition.Domain.
 *
 * ACL (Anti-Corruption Layer) на границе модулей.
 */
final readonly class ChainExecutionDefinitionMapper implements ChainDefinitionProviderInterface
{
    public function __construct(
        private \TaskOrchestrator\Common\Module\ChainDefinition\Application\Service\Chain\ChainLoaderInterface $chainLoader,
    ) {
    }

    #[Override]
    public function loadStaticChainConfig(string $chainName): ExecutionStaticChainConfigVo
    {
        $chain = $this->chainLoader->load($chainName);
        assert($chain instanceof StaticChainDefinitionVo);

        return $this->mapStaticChain($chain);
    }

    #[Override]
    public function loadConditionalChainConfig(string $chainName): ExecutionConditionalChainConfigVo
    {
        $chain = $this->chainLoader->load($chainName);
        assert($chain instanceof ConditionalChainDefinitionVo);

        return $this->mapConditionalChain($chain);
    }

    /**
     * Маппит StaticChainDefinitionVo → ExecutionStaticChainConfigVo.
     */
    public function mapStaticChain(StaticChainDefinitionVo $chain): ExecutionStaticChainConfigVo
    {
        $shared = $chain->getSharedDefinition();

        return new ExecutionStaticChainConfigVo(
            name: $shared->getName(),
            steps: $this->mapSteps($chain->getSteps()),
            fixIterations: $this->mapFixIterations($chain->getFixIterations()),
            budget: $this->mapBudget($shared->getBudget()),
            timeout: $shared->getTimeout(),
            roles: $this->mapRoles($shared->getRoles()),
            defaultRetryPolicy: $this->mapRetryPolicy($chain->getDefaultRetryPolicy()),
        );
    }

    /**
     * Маппит ConditionalChainDefinitionVo → ExecutionConditionalChainConfigVo.
     */
    public function mapConditionalChain(ConditionalChainDefinitionVo $chain): ExecutionConditionalChainConfigVo
    {
        $shared = $chain->getSharedDefinition();

        return new ExecutionConditionalChainConfigVo(
            name: $shared->getName(),
            steps: $this->mapSteps($chain->getSteps()),
            budget: $this->mapBudget($shared->getBudget()),
            timeout: $shared->getTimeout(),
            roles: $this->mapRoles($shared->getRoles()),
        );
    }

    /**
     * @param list<ChainStepVo> $steps
     * @return list<ExecutionStepVo>
     */
    private function mapSteps(array $steps): array
    {
        return array_map(
            fn(ChainStepVo $step): ExecutionStepVo => $this->mapStep($step),
            $steps,
        );
    }

    private function mapStep(ChainStepVo $step): ExecutionStepVo
    {
        $when = $step->getWhen();
        $conditionVo = $when !== null ? $this->mapCondition($when) : null;

        return new ExecutionStepVo(
            type: ChainStepTypeEnum::from($step->getType()->value),
            role: $step->getRole(),
            runner: $step->getRunner(),
            tools: $step->getTools(),
            model: $step->getModel(),
            retryPolicy: $this->mapRetryPolicy($step->getRetryPolicy()),
            name: $step->getName(),
            command: $step->getCommand(),
            label: $step->getLabel(),
            timeoutSeconds: $step->getTimeoutSeconds(),
            noContextFiles: $step->hasNoContextFiles(),
            when: $conditionVo,
            postStep: $step->getPostStep(),
        );
    }

    private function mapCondition(\TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionExpressionVo $condition): ConditionExpressionVo
    {
        return ConditionExpressionVo::fromComponents(
            rawExpression: $condition->getRawExpression(),
            path: $condition->getPath(),
            operator: ConditionOperatorEnum::from($condition->getOperator()->value),
            expectedValue: $condition->getExpectedValue(),
        );
    }

    private function mapBudget(?BudgetVo $budget): ?ExecutionBudgetVo
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

        return new ExecutionBudgetVo(
            maxCostTotal: $budget->getMaxCostTotal(),
            maxCostPerStep: $budget->getMaxCostPerStep(),
            perRoleBudgets: $perRoleBudgets,
        );
    }

    /**
     * @param list<FixIterationGroupVo> $groups
     * @return list<ExecutionFixIterationGroupVo>
     */
    private function mapFixIterations(array $groups): array
    {
        return array_map(
            fn(FixIterationGroupVo $group): ExecutionFixIterationGroupVo => new ExecutionFixIterationGroupVo(
                group: $group->getGroup(),
                stepNames: $group->getStepNames(),
                maxIterations: $group->getMaxIterations(),
            ),
            $groups,
        );
    }

    private function mapRetryPolicy(?\TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo $policy): ?ExecutionRetryPolicyVo
    {
        if ($policy === null) {
            return null;
        }

        return new ExecutionRetryPolicyVo(
            maxRetries: $policy->getMaxRetries(),
            initialDelayMs: $policy->getInitialDelayMs(),
            maxDelayMs: $policy->getMaxDelayMs(),
            multiplier: $policy->getMultiplier(),
        );
    }

    /**
     * @param array<string, RoleConfigVo> $roles
     * @return array<string, ExecutionRoleConfigVo>
     */
    private function mapRoles(array $roles): array
    {
        $result = [];
        foreach ($roles as $role => $config) {
            $result[$role] = new ExecutionRoleConfigVo(
                command: $config->getCommand(),
                timeout: $config->getTimeout(),
                promptFile: $config->getPromptFile(),
                fallback: $this->mapFallback($config->getFallback()),
            );
        }

        return $result;
    }

    private function mapFallback(?FallbackConfigVo $fallback): ?ExecutionFallbackConfigVo
    {
        if ($fallback === null) {
            return null;
        }

        return new ExecutionFallbackConfigVo(
            command: $fallback->getCommand(),
        );
    }
}
