<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Connectivity;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConnectivityRoleTargetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Connectivity\YamlConnectivityRoleTargetProviderService;

#[CoversClass(YamlConnectivityRoleTargetProviderService::class)]
#[CoversClass(ConnectivityRoleTargetVo::class)]
final class YamlConnectivityRoleTargetProviderServiceTest extends TestCase
{
    private string $tempDir;

    #[Override]
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/connectivity-target-provider-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0777, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function readsTopLevelRolesWithoutChains(): void
    {
        $configPath = $this->tempDir . '/chains.yaml';
        file_put_contents($configPath, <<<'YAML'
roles:
  analyst:
    prompt_file: prompts/analyst.md
    timeout: 9
    command: [php, fake-agent.php]
  developer:
    command:
      - php
      - fake-agent.php
YAML);

        $provider = new YamlConnectivityRoleTargetProviderService($configPath);
        $targets = $provider->list();

        self::assertCount(2, $targets);
        self::assertSame('analyst', $targets[0]->getRoleName());
        self::assertSame(['php', 'fake-agent.php'], $targets[0]->getCommand());
        self::assertSame('developer', $targets[1]->getRoleName());
    }

    #[Test]
    public function rejectsRoleWithoutCommandList(): void
    {
        $configPath = $this->tempDir . '/chains.yaml';
        file_put_contents($configPath, <<<'YAML'
roles:
  analyst:
    command: php fake-agent.php
YAML);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Role "analyst" must define command as a list.');

        (new YamlConnectivityRoleTargetProviderService($configPath))->list();
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
