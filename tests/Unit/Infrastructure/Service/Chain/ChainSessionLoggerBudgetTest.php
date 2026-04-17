<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain\ChainSessionLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainSessionLogger::class)]
#[CoversClass(BudgetVo::class)]
final class ChainSessionLoggerBudgetTest extends TestCase
{
    private string $tmpDir;
    private ChainSessionLogger $logger;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/chain_session_budget_test_' . uniqid();
        $this->logger = new ChainSessionLogger($this->tmpDir . '/var/agent/chains', $this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tmpDir);
    }

    #[Test]
    public function sessionJsonContainsBudgetWhenSet(): void
    {
        $budget = new BudgetVo(maxCostTotal: 5.0, maxCostPerStep: 1.5);

        $sessionDir = $this->logger->startSession(
            chainName: 'test_chain',
            topic: 'Test topic',
            facilitator: 'analyst',
            participants: ['dev'],
            maxRounds: 10,
        );
        $this->logger->setBudget($budget);

        $sessionData = $this->readSessionJson($sessionDir);

        self::assertNotNull($sessionData['budget']);
        self::assertEquals(5.0, $sessionData['budget']['max_cost_total']);
        self::assertEquals(1.5, $sessionData['budget']['max_cost_per_step']);
    }

    #[Test]
    public function sessionJsonContainsNullBudgetWhenNotSet(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'test_chain',
            topic: 'Test topic',
            facilitator: 'analyst',
            participants: ['dev'],
            maxRounds: 10,
        );

        $sessionData = $this->readSessionJson($sessionDir);

        self::assertNull($sessionData['budget']);
    }

    #[Test]
    public function interruptSessionWithBudgetExceededContainsBudgetDetails(): void
    {
        $budget = new BudgetVo(maxCostTotal: 3.0, maxCostPerStep: null);

        $sessionDir = $this->logger->startSession(
            chainName: 'test_chain',
            topic: 'Test topic',
            facilitator: 'analyst',
            participants: ['dev'],
            maxRounds: 10,
        );
        $this->logger->setBudget($budget);
        $this->logger->interruptSession('budget_exceeded');

        // Проверяем session.json
        $sessionData = $this->readSessionJson($sessionDir);
        self::assertSame('interrupted', $sessionData['status']);
        self::assertSame('budget_exceeded', $sessionData['completion_reason']);
        self::assertNotNull($sessionData['budget']);
        self::assertEquals(3.0, $sessionData['budget']['max_cost_total']);
        self::assertNull($sessionData['budget']['max_cost_per_step']);

        // Проверяем result.md содержит бюджетную информацию
        $resultContent = file_get_contents($sessionDir . '/result.md');
        self::assertStringContainsString('budget_exceeded', $resultContent);
        self::assertStringContainsString('$3.00', $resultContent);
        self::assertStringContainsString('Budget limit', $resultContent);
        self::assertStringContainsString('unlimited', $resultContent);
    }

    #[Test]
    public function interruptSessionWithoutBudgetExceededHasNoBudgetDetails(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'test_chain',
            topic: 'Test topic',
            facilitator: 'analyst',
            participants: ['dev'],
            maxRounds: 10,
        );
        $this->logger->interruptSession('agent_error');

        $resultContent = file_get_contents($sessionDir . '/result.md');
        self::assertStringContainsString('agent_error', $resultContent);
        self::assertStringNotContainsString('Budget limit', $resultContent);
    }

    #[Test]
    public function setBudgetUpdatesExistingSessionJson(): void
    {
        $sessionDir = $this->logger->startSession(
            chainName: 'test_chain',
            topic: 'Test topic',
            facilitator: 'analyst',
            participants: ['dev'],
            maxRounds: 10,
        );

        // До setBudget — null
        $dataBefore = $this->readSessionJson($sessionDir);
        self::assertNull($dataBefore['budget']);

        // После setBudget — заполнен
        $this->logger->setBudget(new BudgetVo(maxCostTotal: 10.0, maxCostPerStep: 2.0));
        $dataAfter = $this->readSessionJson($sessionDir);
        self::assertEquals(10.0, $dataAfter['budget']['max_cost_total']);
        self::assertEquals(2.0, $dataAfter['budget']['max_cost_per_step']);
    }

    #[Test]
    public function sessionJsonContainsPerRoleBudgetWhenSet(): void
    {
        $analystBudget = new BudgetVo(maxCostTotal: 3.0, maxCostPerStep: 1.0);
        $devBudget = new BudgetVo(maxCostTotal: 5.0);
        $budget = new BudgetVo(maxCostTotal: 10.0, perRoleBudgets: ['analyst' => $analystBudget, 'developer' => $devBudget]);

        $sessionDir = $this->logger->startSession(
            chainName: 'test_chain',
            topic: 'Test topic',
            facilitator: 'analyst',
            participants: ['dev'],
            maxRounds: 10,
        );
        $this->logger->setBudget($budget);

        $sessionData = $this->readSessionJson($sessionDir);

        self::assertNotNull($sessionData['budget']);
        self::assertEquals(10.0, $sessionData['budget']['max_cost_total']);
        self::assertArrayHasKey('per_role', $sessionData['budget']);

        $perRole = $sessionData['budget']['per_role'];
        self::assertArrayHasKey('analyst', $perRole);
        self::assertEquals(3.0, $perRole['analyst']['max_cost_total']);
        self::assertEquals(1.0, $perRole['analyst']['max_cost_per_step']);

        self::assertArrayHasKey('developer', $perRole);
        self::assertEquals(5.0, $perRole['developer']['max_cost_total']);
        self::assertNull($perRole['developer']['max_cost_per_step']);
    }

    #[Test]
    public function interruptSessionWithPerRoleBudgetExceededContainsPerRoleInfo(): void
    {
        $analystBudget = new BudgetVo(maxCostTotal: 3.0);
        $budget = new BudgetVo(maxCostTotal: 10.0, perRoleBudgets: ['analyst' => $analystBudget]);

        $sessionDir = $this->logger->startSession(
            chainName: 'test_chain',
            topic: 'Test topic',
            facilitator: 'analyst',
            participants: ['dev'],
            maxRounds: 10,
        );
        $this->logger->setBudget($budget);
        $this->logger->interruptSession('budget_exceeded');

        $resultContent = file_get_contents($sessionDir . '/result.md');
        self::assertStringContainsString('Per-role budgets:', $resultContent);
        self::assertStringContainsString('analyst:', $resultContent);
        self::assertStringContainsString('$3.00', $resultContent);
    }

    /**
     * @return array<string, mixed>
     */
    private function readSessionJson(string $sessionDir): array
    {
        $content = file_get_contents($sessionDir . '/session.json');
        self::assertNotFalse($content);

        return json_decode($content, true);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
