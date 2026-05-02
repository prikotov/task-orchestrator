<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Infrastructure\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Exception\ExecPolicyViolationException;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\ExecPolicyCheckService;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\SecurityPolicyServiceInterface;
use TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Service\DefaultSecurityPolicyFactory;

#[CoversClass(DefaultSecurityPolicyFactory::class)]
final class DefaultSecurityPolicyFactoryTest extends TestCase
{
    private DefaultSecurityPolicyFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new DefaultSecurityPolicyFactory(
            execPolicyCheckService: new ExecPolicyCheckService(),
        );
    }

    // ─── create: default rules ─────────────────────────────────────────

    #[Test]
    public function createReturnsSecurityPolicyServiceInterface(): void
    {
        $service = $this->factory->create();

        $this->assertInstanceOf(SecurityPolicyServiceInterface::class, $service);
    }

    #[Test]
    public function defaultRulesDenyBashC(): void
    {
        $service = $this->factory->create();

        $this->expectException(ExecPolicyViolationException::class);

        $service->checkRunnerCommand('openai', 'bash -c rm -rf /');
    }

    #[Test]
    public function defaultRulesDenyRmRfRoot(): void
    {
        $service = $this->factory->create();

        $this->expectException(ExecPolicyViolationException::class);

        $service->checkRunnerCommand('openai', 'rm -rf /');
    }

    #[Test]
    public function defaultRulesDenySudo(): void
    {
        $service = $this->factory->create();

        $this->expectException(ExecPolicyViolationException::class);

        $service->checkRunnerCommand('openai', 'sudo apt install something');
    }

    #[Test]
    public function defaultRulesDenyRmRfNoPreserveRoot(): void
    {
        $service = $this->factory->create();

        $this->expectException(ExecPolicyViolationException::class);

        $service->checkRunnerCommand('openai', 'rm -rf * --no-preserve-root');
    }

    #[Test]
    public function defaultRulesAllowSafeCommands(): void
    {
        $service = $this->factory->create();

        // No exception — safe commands are allowed
        $service->checkRunnerCommand('openai', 'Review the code and suggest improvements');
        $service->checkRunnerCommand('openai', 'ls -la');

        $this->assertTrue(true);
    }

    #[Test]
    public function defaultRulesAllowAnyRunner(): void
    {
        $service = $this->factory->create();

        // All runners are allowed by default permission set
        $service->checkRunnerCommand('openai', 'safe task');
        $service->checkRunnerCommand('anthropic', 'safe task');
        $service->checkRunnerCommand('local-shell', 'ls -la');

        $this->assertTrue(true);
    }

    #[Test]
    public function defaultRulesAllowAllChains(): void
    {
        $service = $this->factory->create();

        // Allow-by-default permission set — all chains allowed
        $service->checkChainExecution('code-review', 'static');
        $service->checkChainExecution('brainstorm', 'dynamic');
        $service->checkChainExecution('any-chain', 'conditional');

        $this->assertTrue(true);
    }

    #[Test]
    public function defaultRulesDenyShellBashC(): void
    {
        $service = $this->factory->create();

        $this->expectException(ExecPolicyViolationException::class);

        $service->checkShellCommand('bash -c "rm -rf /"');
    }

    #[Test]
    public function defaultRulesAllowSafeShellCommands(): void
    {
        $service = $this->factory->create();

        $service->checkShellCommand('phpunit tests/');
        $service->checkShellCommand('grep -r "pattern" src/');
        $service->checkShellCommand('cat README.md');

        $this->assertTrue(true);
    }
}
