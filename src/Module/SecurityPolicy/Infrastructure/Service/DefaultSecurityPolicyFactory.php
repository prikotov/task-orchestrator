<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Service;

use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Entity\ExecRule;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleSeverityEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\ExecPolicyCheckServiceInterface;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\SecurityPolicyService;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\SecurityPolicyServiceInterface;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\ExecRuleIdVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\PermissionSetVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\RulePatternVo;

/**
 * Фабрика для создания SecurityPolicyService с default rules (Sprint 9).
 *
 * Создаёт SecurityPolicyService с hardcoded набором ExecRule:
 * - banned prefixes: bash -c, rm -rf /, sudo
 * - allowed runners: all by default (no runner restrictions)
 * - no tool restrictions by default
 *
 * PermissionSet: allow-by-default (все цепочки и runner'ы разрешены,
 * exec rules фильтруют только опасные команды).
 *
 * В Task 5 (YAML DSL) будет добавлен YamlExecRuleRepository,
 * и factory будет заменена на YAML-based provider.
 *
 * @todo Sprint 10+ — заменить hardcoded rules на YAML-based loading (Task 5)
 */
final readonly class DefaultSecurityPolicyFactory
{
    public function __construct(
        private ExecPolicyCheckServiceInterface $execPolicyCheckService,
    ) {
    }

    /**
     * Создаёт SecurityPolicyService с default security rules.
     */
    public function create(): SecurityPolicyServiceInterface
    {
        return new SecurityPolicyService(
            execPolicyCheckService: $this->execPolicyCheckService,
            execRules: $this->createDefaultRules(),
            permissionSet: $this->createDefaultPermissionSet(),
        );
    }

    /**
     * Создаёт default exec rules для Sprint 9.
     *
     * @return list<ExecRule>
     */
    private function createDefaultRules(): array
    {
        return [
            // Banned command prefixes (high priority)
            new ExecRule(
                id: ExecRuleIdVo::createFromString('deny-bash-c'),
                target: RuleTargetEnum::command,
                pattern: RulePatternVo::createFromGlob('bash -c*'),
                action: RuleActionEnum::deny,
                severity: RuleSeverityEnum::block,
                priority: 100,
                description: 'Deny inline bash execution via "bash -c"',
            ),
            new ExecRule(
                id: ExecRuleIdVo::createFromString('deny-rm-rf'),
                target: RuleTargetEnum::command,
                pattern: RulePatternVo::createFromGlob('rm -rf /*'),
                action: RuleActionEnum::deny,
                severity: RuleSeverityEnum::block,
                priority: 100,
                description: 'Deny recursive force delete from root',
            ),
            new ExecRule(
                id: ExecRuleIdVo::createFromString('deny-rm-rf-no-preserve'),
                target: RuleTargetEnum::command,
                pattern: RulePatternVo::createFromGlob('rm -rf * --no-preserve-root*'),
                action: RuleActionEnum::deny,
                severity: RuleSeverityEnum::block,
                priority: 100,
                description: 'Deny recursive force delete with no-preserve-root',
            ),
            new ExecRule(
                id: ExecRuleIdVo::createFromString('deny-sudo'),
                target: RuleTargetEnum::command,
                pattern: RulePatternVo::createFromGlob('sudo*'),
                action: RuleActionEnum::deny,
                severity: RuleSeverityEnum::block,
                priority: 100,
                description: 'Deny sudo commands',
            ),

            // Allow-all catch-all (low priority — all safe commands pass)
            new ExecRule(
                id: ExecRuleIdVo::createFromString('allow-all-command'),
                target: RuleTargetEnum::command,
                pattern: RulePatternVo::createFromGlob('*'),
                action: RuleActionEnum::allow,
                severity: RuleSeverityEnum::block,
                priority: -1000,
                description: 'Default allow for all commands',
            ),
        ];
    }

    /**
     * Создаёт default PermissionSet — allow-by-default.
     *
     * Все цепочки и runner'ы разрешены. Exec rules фильтруют
     * только опасные команды.
     */
    private function createDefaultPermissionSet(): PermissionSetVo
    {
        return PermissionSetVo::createDefaultAllow();
    }
}
