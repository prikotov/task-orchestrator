<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Domain\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Entity\ExecRule;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\ExecPolicyViolationException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\SecurityPolicyViolationException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\ExecPolicyCheckService;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\SecurityPolicyService;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\ExecRuleIdVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\PermissionSetVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\PermissionVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\RulePatternVo;

#[CoversClass(SecurityPolicyService::class)]
final class SecurityPolicyServiceTest extends TestCase
{
    /**
     * @param list<ExecRule> $execRules exec rules (default: allow-all для command/tool)
     */
    private function createService(
        array $execRules = [],
        ?PermissionSetVo $permissionSet = null,
        bool $withDefaultAllow = true,
    ): SecurityPolicyService {
        $rules = $execRules;
        if ($withDefaultAllow) {
            // Добавляем allow-all правило для command и tool, чтобы тесты
            // могли проверять конкретные deny без блокировки всего
            $rules[] = new ExecRule(
                id: ExecRuleIdVo::createFromString('allow-all-cmd'),
                target: RuleTargetEnum::command,
                pattern: RulePatternVo::createFromGlob('*'),
                action: RuleActionEnum::allow,
                priority: -1000,
            );
            $rules[] = new ExecRule(
                id: ExecRuleIdVo::createFromString('allow-all-tool'),
                target: RuleTargetEnum::tool,
                pattern: RulePatternVo::createFromGlob('*'),
                action: RuleActionEnum::allow,
                priority: -1000,
            );
        }

        return new SecurityPolicyService(
            execPolicyCheckService: new ExecPolicyCheckService(),
            execRules: $rules,
            permissionSet: $permissionSet ?? PermissionSetVo::createDefaultAllow(),
        );
    }

    // ─── checkChainExecution ────────────────────────────────────────

    #[Test]
    public function checkChainExecutionAllowedWhenPermissionGranted(): void
    {
        $service = $this->createService(
            permissionSet: PermissionSetVo::createFromPermissions([
                PermissionVo::allow(RuleTargetEnum::chain, 'code-review'),
            ]),
        );

        // Не выбрасывает исключение
        $service->checkChainExecution('code-review', 'static');
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function checkChainExecutionThrowsWhenPermissionDenied(): void
    {
        $service = $this->createService(
            permissionSet: PermissionSetVo::createDefaultDeny(),
        );

        $this->expectException(SecurityPolicyViolationException::class);
        $this->expectExceptionMessage('code-review');

        $service->checkChainExecution('code-review', 'static');
    }

    // ─── checkRunnerCommand ─────────────────────────────────────────

    #[Test]
    public function checkRunnerCommandAllowedWhenNoDenies(): void
    {
        $service = $this->createService(
            permissionSet: PermissionSetVo::createFromPermissions([
                PermissionVo::allow(RuleTargetEnum::runner, 'openai'),
            ]),
        );

        // runner openai разрешён, команда "review code" не попадает под deny
        $service->checkRunnerCommand('openai', 'review code');
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function checkRunnerCommandThrowsForDeniedRunner(): void
    {
        $service = $this->createService(
            permissionSet: PermissionSetVo::createFromPermissions([
                PermissionVo::deny(RuleTargetEnum::runner, 'local-shell'),
            ]),
        );

        $this->expectException(ExecPolicyViolationException::class);
        $this->expectExceptionMessage('local-shell');

        $service->checkRunnerCommand('local-shell', 'echo hi');
    }

    #[Test]
    public function checkRunnerCommandThrowsForDeniedCommandPattern(): void
    {
        $execRules = [
            new ExecRule(
                id: ExecRuleIdVo::createFromString('deny-rm'),
                target: RuleTargetEnum::command,
                pattern: RulePatternVo::createFromGlob('rm *'),
                action: RuleActionEnum::deny,
            ),
        ];

        $service = $this->createService(
            execRules: $execRules,
            permissionSet: PermissionSetVo::createDefaultAllow(),
        );

        $this->expectException(ExecPolicyViolationException::class);
        $this->expectExceptionMessage('deny-rm');

        $service->checkRunnerCommand('openai', 'rm -rf /');
    }

    #[Test]
    public function checkRunnerCommandChecksToolsWhenProvided(): void
    {
        $execRules = [
            new ExecRule(
                id: ExecRuleIdVo::createFromString('deny-sudo'),
                target: RuleTargetEnum::tool,
                pattern: RulePatternVo::createFromExact('sudo'),
                action: RuleActionEnum::deny,
            ),
        ];

        $service = $this->createService(
            execRules: $execRules,
            permissionSet: PermissionSetVo::createDefaultAllow(),
        );

        $this->expectException(ExecPolicyViolationException::class);
        $this->expectExceptionMessage('deny-sudo');

        $service->checkRunnerCommand('openai', 'review code', 'sudo');
    }

    #[Test]
    public function checkRunnerCommandSkipsToolsCheckWhenNull(): void
    {
        $execRules = [
            new ExecRule(
                id: ExecRuleIdVo::createFromString('deny-sudo'),
                target: RuleTargetEnum::tool,
                pattern: RulePatternVo::createFromExact('sudo'),
                action: RuleActionEnum::deny,
            ),
        ];

        $service = $this->createService(
            execRules: $execRules,
            permissionSet: PermissionSetVo::createDefaultAllow(),
        );

        // tools=null → проверка tools не выполняется
        $service->checkRunnerCommand('openai', 'review code', null);
        $this->expectNotToPerformAssertions();
    }

    // ─── checkShellCommand ──────────────────────────────────────────

    #[Test]
    public function checkShellCommandAllowedWhenNoDenyMatches(): void
    {
        $service = $this->createService();

        $service->checkShellCommand('echo hello');
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function checkShellCommandThrowsForDeniedPattern(): void
    {
        $execRules = [
            new ExecRule(
                id: ExecRuleIdVo::createFromString('deny-rm-rf'),
                target: RuleTargetEnum::command,
                pattern: RulePatternVo::createFromGlob('rm -rf *'),
                action: RuleActionEnum::deny,
            ),
        ];

        $service = $this->createService(
            execRules: $execRules,
            permissionSet: PermissionSetVo::createDefaultAllow(),
        );

        $this->expectException(ExecPolicyViolationException::class);
        $this->expectExceptionMessage('deny-rm-rf');

        $service->checkShellCommand('rm -rf /');
    }

    #[Test]
    public function checkShellCommandDefaultDenyWhenNoRules(): void
    {
        $service = $this->createService(
            execRules: [],
            permissionSet: PermissionSetVo::createDefaultAllow(),
            withDefaultAllow: false,
        );

        // Пустые exec rules → default deny от ExecPolicyCheckService
        $this->expectException(ExecPolicyViolationException::class);
        $this->expectExceptionMessage('default-deny');

        $service->checkShellCommand('echo hello');
    }

    // ─── ExecPolicyViolationException contains rule info ────────────

    #[Test]
    public function violationExceptionContainsRuleInfo(): void
    {
        $execRules = [
            new ExecRule(
                id: ExecRuleIdVo::createFromString('deny-bash-c'),
                target: RuleTargetEnum::command,
                pattern: RulePatternVo::createFromGlob('bash -c *'),
                action: RuleActionEnum::deny,
            ),
        ];

        $service = $this->createService(
            execRules: $execRules,
            permissionSet: PermissionSetVo::createDefaultAllow(),
        );

        try {
            $service->checkShellCommand('bash -c echo');
            self::fail('Expected ExecPolicyViolationException');
        } catch (ExecPolicyViolationException $e) {
            self::assertSame('deny-bash-c', $e->getRuleId());
            self::assertSame(RuleTargetEnum::command, $e->getTarget());
            self::assertSame('bash -c *', $e->getPattern());
            self::assertSame('bash -c echo', $e->getViolatedValue());
        }
    }
}
