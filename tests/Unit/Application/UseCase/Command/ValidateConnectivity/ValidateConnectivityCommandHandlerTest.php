<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\UseCase\Command\ValidateConnectivity;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Enum\ConnectivityStatusEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity\ConnectivityRoleResultDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity\ValidateConnectivityCommand;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity\ValidateConnectivityCommandHandler;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity\ValidateConnectivityResultDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Connectivity\ConnectivityCommandResolverInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Connectivity\ConnectivityProcessRunnerInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Connectivity\ConnectivityRoleTargetProviderInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityProcessRequestVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityProcessResultVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityResolvedCommandVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityRoleTargetVo;

#[CoversClass(ValidateConnectivityCommandHandler::class)]
#[CoversClass(ValidateConnectivityCommand::class)]
#[CoversClass(ValidateConnectivityResultDto::class)]
#[CoversClass(ConnectivityRoleResultDto::class)]
final class ValidateConnectivityCommandHandlerTest extends TestCase
{
    private FakeRoleTargetProvider $provider;
    private FakeConnectivityCommandResolver $resolver;
    private FakeConnectivityProcessRunner $runner;
    private ValidateConnectivityCommandHandler $handler;

    #[Override]
    protected function setUp(): void
    {
        $this->provider = new FakeRoleTargetProvider([
            new ConnectivityRoleTargetVo('analyst', ['fake-agent', '--role', 'analyst']),
            new ConnectivityRoleTargetVo('developer', ['fake-agent', '--role', 'developer']),
        ]);
        $this->resolver = new FakeConnectivityCommandResolver();
        $this->runner = new FakeConnectivityProcessRunner();
        $this->handler = new ValidateConnectivityCommandHandler($this->provider, $this->resolver, $this->runner);
    }

    #[Test]
    public function dryRunReturnsTargetsWithoutProcessExecution(): void
    {
        $result = ($this->handler)(new ValidateConnectivityCommand(dryRun: true));

        self::assertFalse($result->hasFailures);
        self::assertTrue($result->dryRun);
        self::assertSame(0, $this->runner->callCount);
        self::assertCount(2, $result->results);
        self::assertSame(ConnectivityStatusEnum::dryRun, $result->results[0]->status);
        self::assertSame('fake-agent --role analyst resolved-user-prompt', $result->results[0]->commandPreview);
        self::assertSame(['analyst', 'developer'], $this->resolver->cleanupRoleNames);
    }

    #[Test]
    public function runsSelectedRoleOnly(): void
    {
        $this->runner->queueResult(new ConnectivityProcessResultVo(0, 'ok', '', 0.1));

        $result = ($this->handler)(new ValidateConnectivityCommand(roleName: 'developer', timeout: 7));

        self::assertFalse($result->hasFailures);
        self::assertCount(1, $result->results);
        self::assertSame('developer', $result->results[0]->role);
        self::assertSame(ConnectivityStatusEnum::ok, $result->results[0]->status);
        self::assertCount(1, $this->runner->requests);
        self::assertSame(7, $this->runner->requests[0]->getTimeout());
        self::assertSame('developer', $this->runner->requests[0]->getRoleName());
        self::assertSame(['fake-agent', '--role', 'developer', 'resolved-user-prompt'], $this->runner->requests[0]->getCommand());
        self::assertNull($this->runner->requests[0]->getStdinPrompt());
        self::assertSame(['developer'], $this->resolver->cleanupRoleNames);
    }

    #[Test]
    public function mapsExitCodeFailureEmptyOutputAndTimeout(): void
    {
        $this->provider = new FakeRoleTargetProvider([
            new ConnectivityRoleTargetVo('ok', ['fake-agent']),
            new ConnectivityRoleTargetVo('exit_fail', ['fake-agent']),
            new ConnectivityRoleTargetVo('empty', ['fake-agent']),
            new ConnectivityRoleTargetVo('timeout', ['fake-agent']),
        ]);
        $this->handler = new ValidateConnectivityCommandHandler($this->provider, $this->resolver, $this->runner);
        $this->runner->queueResult(new ConnectivityProcessResultVo(0, 'ok', '', 0.1));
        $this->runner->queueResult(new ConnectivityProcessResultVo(2, '', 'model not found', 0.2));
        $this->runner->queueResult(new ConnectivityProcessResultVo(0, "\n", '', 0.3));
        $this->runner->queueResult(new ConnectivityProcessResultVo(1, '', 'timeout', 1.0, true));

        $result = ($this->handler)(new ValidateConnectivityCommand(timeout: 5));

        self::assertTrue($result->hasFailures);
        self::assertSame([
            ConnectivityStatusEnum::ok,
            ConnectivityStatusEnum::fail,
            ConnectivityStatusEnum::fail,
            ConnectivityStatusEnum::timeout,
        ], array_map(static fn(ConnectivityRoleResultDto $row): ConnectivityStatusEnum => $row->status, $result->results));
        self::assertSame('exit code 2: model not found', $result->results[1]->error);
        self::assertSame('empty output', $result->results[2]->error);
        self::assertSame('timeout', $result->results[3]->error);
    }

    #[Test]
    public function processesRolesSequentiallyInConfigOrder(): void
    {
        $this->runner->queueResult(new ConnectivityProcessResultVo(0, 'ok', '', 0.1));
        $this->runner->queueResult(new ConnectivityProcessResultVo(0, 'ok', '', 0.1));

        ($this->handler)(new ValidateConnectivityCommand());

        self::assertSame(['analyst', 'developer'], $this->runner->requestRoleNames);
    }

    #[Test]
    public function missingRoleIsInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role "missing" not found');

        ($this->handler)(new ValidateConnectivityCommand(roleName: 'missing'));
    }

    #[Test]
    public function nonPositiveTimeoutIsInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--timeout must be a positive integer.');

        ($this->handler)(new ValidateConnectivityCommand(timeout: 0));
    }
}

/** @internal */
final class FakeRoleTargetProvider implements ConnectivityRoleTargetProviderInterface
{
    /**
     * @param list<ConnectivityRoleTargetVo> $targets
     */
    public function __construct(
        private readonly array $targets,
    ) {
    }

    /**
     * @return list<ConnectivityRoleTargetVo>
     */
    #[Override]
    public function list(?string $configPath = null): array
    {
        return $this->targets;
    }
}

/** @internal */
final class FakeConnectivityCommandResolver implements ConnectivityCommandResolverInterface
{
    /** @var list<string> */
    public array $cleanupRoleNames = [];

    #[Override]
    public function resolve(ConnectivityRoleTargetVo $target): ConnectivityResolvedCommandVo
    {
        return new ConnectivityResolvedCommandVo(
            roleName: $target->getRoleName(),
            command: [...$target->getCommand(), 'resolved-user-prompt'],
            cleanupPaths: [],
        );
    }

    #[Override]
    public function cleanup(ConnectivityResolvedCommandVo $resolvedCommand): void
    {
        $this->cleanupRoleNames[] = $resolvedCommand->getRoleName();
    }
}

/** @internal */
final class FakeConnectivityProcessRunner implements ConnectivityProcessRunnerInterface
{
    public int $callCount = 0;

    /** @var list<ConnectivityProcessRequestVo> */
    public array $requests = [];

    /** @var list<string> */
    public array $requestRoleNames = [];

    /** @var list<ConnectivityProcessResultVo> */
    private array $queuedResults = [];

    public function queueResult(ConnectivityProcessResultVo $result): void
    {
        $this->queuedResults[] = $result;
    }

    #[Override]
    public function run(ConnectivityProcessRequestVo $request): ConnectivityProcessResultVo
    {
        $this->callCount++;
        $this->requests[] = $request;
        $this->requestRoleNames[] = $request->getRoleName();

        return array_shift($this->queuedResults) ?? new ConnectivityProcessResultVo(0, 'ok', '', 0.1);
    }
}
