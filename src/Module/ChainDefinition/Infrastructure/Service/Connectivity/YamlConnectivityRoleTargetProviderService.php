<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Connectivity;

use InvalidArgumentException;
use Override;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Connectivity\ConnectivityRoleTargetProviderInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityRoleTargetVo;

/**
 * YAML implementation (реализация) чтения top-level roles для проверки связности.
 */
final readonly class YamlConnectivityRoleTargetProviderService implements ConnectivityRoleTargetProviderInterface
{
    public function __construct(
        private string $yamlPath,
    ) {
    }

    /**
     * @return list<ConnectivityRoleTargetVo>
     */
    #[Override]
    public function list(?string $configPath = null): array
    {
        $path = $configPath !== null && $configPath !== '' ? $configPath : $this->yamlPath;
        if (!file_exists($path)) {
            throw new InvalidArgumentException(sprintf('Config file not found: %s', $path));
        }

        try {
            $yaml = Yaml::parseFile($path);
        } catch (ParseException $e) {
            throw new InvalidArgumentException(sprintf('Invalid YAML in config "%s": %s', $path, $e->getMessage()), previous: $e);
        }

        if (!is_array($yaml)) {
            throw new InvalidArgumentException(sprintf('Config file "%s" must contain a YAML mapping.', $path));
        }

        $roles = $yaml['roles'] ?? [];
        if (!is_array($roles)) {
            throw new InvalidArgumentException('Top-level "roles" section must be a mapping.');
        }

        return $this->mapRoles($roles);
    }

    /**
     * @param array<array-key, mixed> $roles
     * @return list<ConnectivityRoleTargetVo>
     */
    private function mapRoles(array $roles): array
    {
        $targets = [];
        foreach ($roles as $roleName => $rawRole) {
            if (!is_string($roleName) || trim($roleName) === '') {
                throw new InvalidArgumentException('Top-level role name must be a non-empty string.');
            }

            if (!is_array($rawRole)) {
                throw new InvalidArgumentException(sprintf('Role "%s" config must be a mapping.', $roleName));
            }

            $command = $rawRole['command'] ?? null;
            if (!is_array($command)) {
                throw new InvalidArgumentException(sprintf('Role "%s" must define command as a list.', $roleName));
            }

            $targets[] = new ConnectivityRoleTargetVo(
                roleName: $roleName,
                command: $this->normalizeCommand($roleName, $command),
            );
        }

        return $targets;
    }

    /**
     * @param array<array-key, mixed> $command
     * @return list<string>
     */
    private function normalizeCommand(string $roleName, array $command): array
    {
        if ($command === []) {
            throw new InvalidArgumentException(sprintf('Role "%s" command must not be empty.', $roleName));
        }

        $normalized = [];
        foreach (array_values($command) as $argument) {
            if (!is_scalar($argument)) {
                throw new InvalidArgumentException(sprintf('Role "%s" command arguments must be scalar.', $roleName));
            }

            $normalized[] = (string) $argument;
        }

        return $normalized;
    }

}
