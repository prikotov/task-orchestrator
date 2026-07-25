<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Module\GitIdentity\Command;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;
use TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken\ObtainTokenCommandHandler;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\OpenSslSignJwtTokenService;
use TaskOrchestrator\Console\Module\GitIdentity\Command\AgentTokenCommand;
use TaskOrchestrator\Tests\Integration\Module\GitIdentity\Stub\FixedClockService;
use TaskOrchestrator\Tests\Integration\Module\GitIdentity\Stub\InMemoryTokenCache;
use TaskOrchestrator\Tests\Integration\Module\GitIdentity\Stub\StubGitIdentityConfigLoader;
use TaskOrchestrator\Tests\Integration\Module\GitIdentity\Stub\StubRequestInstallationTokenService;
use TaskOrchestrator\Tests\Integration\Module\GitIdentity\Stub\StubResolveInstallationIdService;

#[CoversClass(AgentTokenCommand::class)]
final class AgentTokenCommandTest extends TestCase
{
    private const FIXTURE_PEM = __DIR__ . '/../../../../Unit/Module/GitIdentity/fixtures/test-private-key.pem';

    private const REPO = 'octocat/Hello-World';

    private DateTimeImmutable $now;

    private InstallationIdVo $installationId;

    private InMemoryTokenCache $cache;

    private StubResolveInstallationIdService $resolver;

    private StubRequestInstallationTokenService $requester;

    private ObtainTokenCommandHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable();
        $now = $this->now;
        $nowClosure = static fn (): DateTimeImmutable => $now;

        $this->installationId = new InstallationIdVo(424242);
        $this->cache = new InMemoryTokenCache($nowClosure);
        $this->resolver = new StubResolveInstallationIdService($this->installationId);
        $this->requester = new StubRequestInstallationTokenService(
            $this->installationId,
            'ghs_integration_token',
            $nowClosure,
        );

        $configLoader = new StubGitIdentityConfigLoader((string) file_get_contents(self::FIXTURE_PEM));
        $clock = new FixedClockService($this->now);
        $signer = new OpenSslSignJwtTokenService();

        $this->handler = new ObtainTokenCommandHandler(
            $configLoader,
            $this->cache,
            $signer,
            $this->resolver,
            $this->requester,
            $clock,
        );
    }

    private function execute(array $input): CommandTester
    {
        $command = new AgentTokenCommand($this->handler);
        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    #[Test]
    public function helpOptionDescribesCommand(): void
    {
        // --help is a global option handled by the Application, so route through ApplicationTester
        // (this also verifies the command registers in the console application).
        $application = new Application();
        $application->addCommand(new AgentTokenCommand($this->handler));
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'agent:token', '--help' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('agent:token', $tester->getDisplay());
        self::assertStringContainsString('installation token', $tester->getDisplay());
        self::assertStringContainsString('--format', $tester->getDisplay());
        self::assertStringContainsString('owner>/<repo>', $tester->getDisplay());
    }

    #[Test]
    public function commandDeclaresRepoArgumentAndFormatOption(): void
    {
        $command = new AgentTokenCommand($this->handler);

        self::assertSame('agent:token', (string) $command->getName());
        self::assertNotNull($command->getDefinition()->getArgument('repo'));
        self::assertTrue($command->getDefinition()->getArgument('repo')->isRequired());
        self::assertNotNull($command->getDefinition()->getOption('format'));
    }

    #[Test]
    public function missingRepoArgumentFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/repo/');

        $this->execute([]);
    }

    #[Test]
    public function invalidRepoSlugReturnsFailureWithSanitizedMessage(): void
    {
        $tester = $this->execute(['repo' => 'not-a-valid-slug']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Invalid repository slug', $tester->getDisplay());
    }

    #[Test]
    public function unknownFormatReturnsInvalidExitCode(): void
    {
        $tester = $this->execute(['repo' => self::REPO, '--format' => 'xml']);

        self::assertSame(2, $tester->getStatusCode());
        self::assertStringContainsString('Unknown --format', $tester->getDisplay());
    }

    #[Test]
    public function plainFormatPrintsTokenOnly(): void
    {
        $tester = $this->execute(['repo' => self::REPO, '--format' => 'plain']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('ghs_integration_token', trim($tester->getDisplay()));
        // No JSON markers in plain output.
        self::assertStringNotContainsString('{', $tester->getDisplay());
        self::assertStringNotContainsString('export', $tester->getDisplay());
    }

    #[Test]
    public function plainIsDefaultFormat(): void
    {
        $tester = $this->execute(['repo' => self::REPO]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('ghs_integration_token', trim($tester->getDisplay()));
    }

    #[Test]
    public function jsonFormatPrintsStructuredPayload(): void
    {
        $tester = $this->execute(['repo' => self::REPO, '--format' => 'json']);

        self::assertSame(0, $tester->getStatusCode());

        /** @var array{token: string, expires_at: string, installation_id: int} $payload */
        $payload = json_decode(trim($tester->getDisplay()), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ghs_integration_token', $payload['token']);
        self::assertSame(424242, $payload['installation_id']);
        // expires_at must be a parseable ISO-8601 timestamp.
        $expiresAt = new DateTimeImmutable($payload['expires_at']);
        self::assertSame(
            $this->now->modify('+3600 seconds')->getTimestamp(),
            $expiresAt->getTimestamp(),
        );
    }

    #[Test]
    public function envFormatPrintsShellExportStatement(): void
    {
        $tester = $this->execute(['repo' => self::REPO, '--format' => 'env']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame("export GITHUB_TOKEN='ghs_integration_token'", trim($tester->getDisplay()));
    }

    #[Test]
    public function githubApiErrorIsReportedAsFailure(): void
    {
        $this->resolver = new StubResolveInstallationIdService($this->installationId, shouldFail: true);
        $this->rebuildHandler();

        $tester = $this->execute(['repo' => self::REPO]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('HTTP 404', $tester->getDisplay());
        // Token value must never leak into error output.
        self::assertStringNotContainsString('ghs_integration_token', $tester->getDisplay());
    }

    #[Test]
    public function tokenWithUnsafeCharactersAbortsForSafety(): void
    {
        $now = $this->now;
        $nowClosure = static fn (): DateTimeImmutable => $now;
        $this->requester = new StubRequestInstallationTokenService(
            $this->installationId,
            'ghs_unsafe-token!', // contains '!' which is NOT in [A-Za-z0-9_.-] — rejected.
            // Note: hyphen is now permitted (stateless ghs_<APPID>_<JWT> format),
            // but bang is still forbidden.
            $nowClosure,
        );
        $this->rebuildHandler();

        $tester = $this->execute(['repo' => self::REPO]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('unexpected characters', $tester->getDisplay());
        self::assertStringNotContainsString('ghs_unsafe-token!', $tester->getDisplay());
    }

    #[Test]
    public function statelessTokenFormatWithDotsAndHyphensIsAccepted(): void
    {
        // Since 2026-04-27 GitHub rolls out stateless installation tokens of the
        // form `ghs_<APPID>_<JWT>`: JWT body/header contain '.' (header.payload.signature
        // segments) and '-' (Base64URL). These MUST pass the safety pattern.
        // Synthetic low-entropy fixture value — deliberately NOT a realistic JWT
        // (no `eyJ`, no real installation-ID) to avoid tripping gitleaks' generic-api-key
        // rule, while still exercising '.' and '-' acceptance in the validator.
        $statelessToken = 'ghs_123456_stateless.token-segment';

        $now = $this->now;
        $nowClosure = static fn (): DateTimeImmutable => $now;
        $this->requester = new StubRequestInstallationTokenService(
            $this->installationId,
            $statelessToken,
            $nowClosure,
        );
        $this->rebuildHandler();

        // plain format: token reaches stdout verbatim.
        $plain = $this->execute(['repo' => self::REPO, '--format' => 'plain']);
        self::assertSame(0, $plain->getStatusCode());
        self::assertSame($statelessToken, trim($plain->getDisplay()));

        // env format: token wrapped in shell export statement.
        $env = $this->execute(['repo' => self::REPO, '--format' => 'env']);
        self::assertSame(0, $env->getStatusCode());
        self::assertSame("export GITHUB_TOKEN='{$statelessToken}'", trim($env->getDisplay()));

        // json format: token present in structured payload.
        $json = $this->execute(['repo' => self::REPO, '--format' => 'json']);
        self::assertSame(0, $json->getStatusCode());
        /** @var array{token: string, expires_at: string, installation_id: int} $payload */
        $payload = json_decode(trim($json->getDisplay()), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($statelessToken, $payload['token']);
        self::assertSame(424242, $payload['installation_id']);
    }

    #[Test]
    public function secondInvocationReusesCacheAndSkipsNetwork(): void
    {
        $first = $this->execute(['repo' => self::REPO, '--format' => 'plain']);
        self::assertSame(0, $first->getStatusCode());
        $resolverCallsAfterFirst = $this->resolver->callCount;
        $requesterCallsAfterFirst = $this->requester->callCount;
        self::assertSame(1, $resolverCallsAfterFirst);
        self::assertSame(1, $requesterCallsAfterFirst);

        $second = $this->execute(['repo' => self::REPO, '--format' => 'plain']);
        self::assertSame(0, $second->getStatusCode());
        self::assertSame('ghs_integration_token', trim($second->getDisplay()));

        // Cache-hit: resolver/requester must NOT be called again.
        self::assertSame($resolverCallsAfterFirst, $this->resolver->callCount);
        self::assertSame($requesterCallsAfterFirst, $this->requester->callCount);
    }

    private function rebuildHandler(): void
    {
        $now = $this->now;
        $nowClosure = static fn (): DateTimeImmutable => $now;
        $configLoader = new StubGitIdentityConfigLoader((string) file_get_contents(self::FIXTURE_PEM));
        $clock = new FixedClockService($this->now);
        $signer = new OpenSslSignJwtTokenService();

        $this->handler = new ObtainTokenCommandHandler(
            $configLoader,
            $this->cache,
            $signer,
            $this->resolver,
            $this->requester,
            $clock,
        );

        unset($nowClosure);
    }
}
