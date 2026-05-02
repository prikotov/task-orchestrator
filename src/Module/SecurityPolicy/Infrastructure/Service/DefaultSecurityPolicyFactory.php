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
use TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Persistence\YamlExecRuleRepository;

/**
 * Фабрика для создания SecurityPolicyService с default rules.
 *
 * Загружает exec rules из YAML файла (через YamlExecRuleRepository).
 * Если YAML файл не найден или не содержит rules — fallback на hardcoded defaults.
 * PermissionSet: allow-by-default (все цепочки и runner'ы разрешены,
 * exec rules фильтруют только опасные команды).
 *
 * @see YamlExecRuleRepository
 * @see SecurityPolicyService
 */
final readonly class DefaultSecurityPolicyFactory
{
    public function __construct(
        private ExecPolicyCheckServiceInterface $execPolicyCheckService,
        private YamlExecRuleRepository $yamlExecRuleRepository,
    ) {
    }

    /**
     * Создаёт SecurityPolicyService с security rules из YAML или default fallback.
     */
    public function create(): SecurityPolicyServiceInterface
    {
        $execRules = $this->yamlExecRuleRepository->loadRules();

        // Fallback: если YAML не содержит rules — используем hardcoded defaults
        if ($execRules === []) {
            $execRules = $this->createDefaultRules();
        }

        return new SecurityPolicyService(
            execPolicyCheckService: $this->execPolicyCheckService,
            execRules: $execRules,
            permissionSet: $this->createDefaultPermissionSet(),
        );
    }

    /**
     * Создаёт default exec rules (fallback при отсутствии YAML файла).
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
