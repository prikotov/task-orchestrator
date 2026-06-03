<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\ValidateConnectivity;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Enum\ConnectivityStatusEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Integration\ConnectivityCommandResolverInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Integration\ConnectivityProcessRunnerInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Integration\ConnectivityRoleTargetProviderInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityProcessRequestVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityProcessResultVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityResolvedCommandVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityRoleTargetVo;

/**
 * Use case (сценарий) проверки, что роли из top-level `roles` запускаются и отвечают.
 */
final readonly class ValidateConnectivityCommandHandler
{
    public function __construct(
        private ConnectivityRoleTargetProviderInterface $targetProvider,
        private ConnectivityCommandResolverInterface $commandResolver,
        private ConnectivityProcessRunnerInterface $processRunner,
    ) {
    }

    public function __invoke(ValidateConnectivityCommand $command): ValidateConnectivityResultDto
    {
        if ($command->timeout <= 0) {
            throw new InvalidArgumentException('--timeout must be a positive integer.');
        }

        $targets = $this->selectTargets(
            $this->targetProvider->list($command->configPath),
            $command->roleName,
        );

        $results = [];
        $hasFailures = false;

        foreach ($targets as $target) {
            $resolvedCommand = $this->commandResolver->resolve($target);

            try {
                if ($command->dryRun) {
                    $results[] = new ConnectivityRoleResultDto(
                        role: $target->getRoleName(),
                        status: ConnectivityStatusEnum::dryRun,
                        commandPreview: $this->buildCommandPreview($resolvedCommand),
                    );
                    continue;
                }

                $processResult = $this->processRunner->run(new ConnectivityProcessRequestVo(
                    roleName: $target->getRoleName(),
                    command: $resolvedCommand->getCommand(),
                    timeout: $command->timeout,
                ));

                $roleResult = $this->mapProcessResult($target->getRoleName(), $processResult);
                $results[] = $roleResult;

                if ($roleResult->status !== ConnectivityStatusEnum::ok) {
                    $hasFailures = true;
                }
            } finally {
                $this->commandResolver->cleanup($resolvedCommand);
            }
        }

        return new ValidateConnectivityResultDto($results, $hasFailures, $command->dryRun);
    }

    /**
     * @param list<ConnectivityRoleTargetVo> $targets
     * @return list<ConnectivityRoleTargetVo>
     */
    private function selectTargets(array $targets, ?string $roleName): array
    {
        if ($targets === []) {
            throw new InvalidArgumentException('No top-level roles found in chains.yaml.');
        }

        if ($roleName === null || $roleName === '') {
            return $targets;
        }

        foreach ($targets as $target) {
            if ($target->getRoleName() === $roleName) {
                return [$target];
            }
        }

        throw new InvalidArgumentException(sprintf('Role "%s" not found in top-level roles.', $roleName));
    }

    private function mapProcessResult(string $roleName, ConnectivityProcessResultVo $processResult): ConnectivityRoleResultDto
    {
        if ($processResult->isTimedOut()) {
            return new ConnectivityRoleResultDto(
                role: $roleName,
                status: ConnectivityStatusEnum::timeout,
                durationSeconds: $processResult->getDurationSeconds(),
                error: 'timeout',
            );
        }

        if ($processResult->getExitCode() !== 0) {
            return new ConnectivityRoleResultDto(
                role: $roleName,
                status: ConnectivityStatusEnum::fail,
                durationSeconds: $processResult->getDurationSeconds(),
                error: sprintf(
                    'exit code %d: %s',
                    $processResult->getExitCode(),
                    $this->extractErrorOutput($processResult),
                ),
            );
        }

        if (trim($processResult->getStdout()) === '') {
            return new ConnectivityRoleResultDto(
                role: $roleName,
                status: ConnectivityStatusEnum::fail,
                durationSeconds: $processResult->getDurationSeconds(),
                error: 'empty output',
            );
        }

        return new ConnectivityRoleResultDto(
            role: $roleName,
            status: ConnectivityStatusEnum::ok,
            durationSeconds: $processResult->getDurationSeconds(),
        );
    }

    private function extractErrorOutput(ConnectivityProcessResultVo $processResult): string
    {
        $stderr = trim($processResult->getStderr());
        if ($stderr !== '') {
            return $this->truncate($stderr);
        }

        $stdout = trim($processResult->getStdout());
        if ($stdout !== '') {
            return $this->truncate($stdout);
        }

        return 'no output';
    }

    private function truncate(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', $value) ?? $value;

        if (strlen($normalized) <= 200) {
            return $normalized;
        }

        return substr($normalized, 0, 197) . '...';
    }

    private function buildCommandPreview(ConnectivityResolvedCommandVo $resolvedCommand): string
    {
        return implode(' ', array_map($this->quoteCommandPart(...), $resolvedCommand->getCommand()));
    }

    private function quoteCommandPart(string $part): string
    {
        if ($part === '' || preg_match('/\s/', $part) === 1) {
            return escapeshellarg($part);
        }

        return $part;
    }
}
