<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service;

use Override;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Entity\ExecRule;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\ExecPolicyViolationException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\SecurityPolicyViolationException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\ExecPolicyCheckResultVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\PermissionSetVo;

/**
 * Domain Service: агрегация security policy checks.
 *
 * Объединяет chain-level checks (PermissionSet) и exec-level checks (ExecRule).
 * Использует ExecPolicyCheckService для проверки exec правил.
 * Выбрасывает исключения при нарушениях политики безопасности.
 *
 * В текущей реализации (Sprint 9) exec rules и permission set
 * внедряются через конструктор. В Infrastructure (Task 4) будет
 * добавлена загрузка из конфигурации.
 */
final readonly class SecurityPolicyService implements SecurityPolicyServiceInterface
{
    /**
     * @param ExecPolicyCheckServiceInterface $execPolicyCheckService сервис проверки exec rules
     * @param list<ExecRule> $execRules набор exec rules для проверок
     * @param PermissionSetVo $permissionSet набор permissions для chain-level checks
     */
    public function __construct(
        private ExecPolicyCheckServiceInterface $execPolicyCheckService,
        private array $execRules,
        private PermissionSetVo $permissionSet,
    ) {
    }

    #[Override]
    public function checkChainExecution(string $chainName, string $chainType): void
    {
        // Проверяем, разрешено ли выполнение цепочки по имени
        if (!$this->permissionSet->isAllowed(RuleTargetEnum::chain, $chainName)) {
            throw new SecurityPolicyViolationException(
                $chainName,
                sprintf('Chain execution is not allowed (target: chain, resource: "%s").', $chainName),
            );
        }
    }

    #[Override]
    public function checkRunnerCommand(string $runnerName, string $task, ?string $tools = null): void
    {
        // Проверяем runner через PermissionSet
        if (!$this->permissionSet->isAllowed(RuleTargetEnum::runner, $runnerName)) {
            throw new ExecPolicyViolationException(
                sprintf('Runner "%s" is not allowed by permission policy.', $runnerName),
            );
        }

        // Проверяем команду (task) через exec rules
        $this->enforceExecPolicy($task, RuleTargetEnum::command);

        // Проверяем tools, если указан
        if ($tools !== null) {
            $this->enforceExecPolicy($tools, RuleTargetEnum::tool);
        }
    }

    #[Override]
    public function checkShellCommand(string $command): void
    {
        $this->enforceExecPolicy($command, RuleTargetEnum::command);
    }

    /**
     * Проверяет exec policy и выбрасывает исключение при нарушении.
     *
     * @throws ExecPolicyViolationException
     */
    private function enforceExecPolicy(string $value, RuleTargetEnum $target): void
    {
        $result = $this->execPolicyCheckService->check($value, $target, $this->execRules);

        if ($result->isAllowed()) {
            return;
        }

        $violatedRule = $result->getViolatedRule();
        if ($violatedRule !== null) {
            throw ExecPolicyViolationException::createFromRule(
                ruleId: $violatedRule->getId()->getValue(),
                target: $violatedRule->getTarget(),
                pattern: $violatedRule->getPattern()->getPattern(),
                violatedValue: $value,
            );
        }

        // Default deny — нет правила, но доступ запрещён
        throw ExecPolicyViolationException::createFromRule(
            ruleId: 'default-deny',
            target: $target,
            pattern: '*',
            violatedValue: $value,
        );
    }
}
