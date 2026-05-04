<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\SharedChainDefinitionVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SharedChainDefinitionVo::class)]
final class SharedChainDefinitionVoTest extends TestCase
{
    #[Test]
    public function holdsNameAndDescription(): void
    {
        $vo = $this->createStaticShared();

        self::assertSame('test-chain', $vo->getName());
        self::assertSame('Test description', $vo->getDescription());
    }

    #[Test]
    public function holdsType(): void
    {
        self::assertSame(ChainTypeEnum::staticType, $this->createStaticShared()->getType());
        self::assertSame(ChainTypeEnum::dynamicType, $this->createDynamicShared()->getType());
    }

    #[Test]
    public function isDynamicReturnsCorrectValue(): void
    {
        self::assertFalse($this->createStaticShared()->isDynamic());
        self::assertTrue($this->createDynamicShared()->isDynamic());
    }

    #[Test]
    public function holdsNullableBudget(): void
    {
        $withoutBudget = $this->createStaticShared();
        self::assertNull($withoutBudget->getBudget());

        $budget = new BudgetVo(maxCostTotal: 10.0);
        $withBudget = new SharedChainDefinitionVo(
            name: 'budget-chain',
            description: '',
            type: ChainTypeEnum::staticType,
            budget: $budget,
            timeout: null,
            maxTime: null,
            roles: [],
        );
        self::assertNotNull($withBudget->getBudget());
        self::assertSame(10.0, $withBudget->getBudget()->getMaxCostTotal());
    }

    #[Test]
    public function holdsNullableTimeout(): void
    {
        $withoutTimeout = $this->createStaticShared();
        self::assertNull($withoutTimeout->getTimeout());

        $withTimeout = new SharedChainDefinitionVo(
            name: 'timed',
            description: '',
            type: ChainTypeEnum::staticType,
            budget: null,
            timeout: 600,
            maxTime: null,
            roles: [],
        );
        self::assertSame(600, $withTimeout->getTimeout());
    }

    #[Test]
    public function holdsNullableMaxTime(): void
    {
        $withoutMaxTime = $this->createStaticShared();
        self::assertNull($withoutMaxTime->getMaxTime());

        $withMaxTime = new SharedChainDefinitionVo(
            name: 'maxtimed',
            description: '',
            type: ChainTypeEnum::staticType,
            budget: null,
            timeout: null,
            maxTime: 1800,
            roles: [],
        );
        self::assertSame(1800, $withMaxTime->getMaxTime());
    }

    #[Test]
    public function holdsRoles(): void
    {
        $roles = [
            'analyst' => new RoleConfigVo(command: ['pi', '--model', 'gpt-4o-mini']),
            'developer' => new RoleConfigVo(command: ['pi'], timeout: 300),
        ];

        $vo = new SharedChainDefinitionVo(
            name: 'roles-chain',
            description: '',
            type: ChainTypeEnum::staticType,
            budget: null,
            timeout: null,
            maxTime: null,
            roles: $roles,
        );

        self::assertSame($roles, $vo->getRoles());
        self::assertSame(['pi', '--model', 'gpt-4o-mini'], $vo->getRoleConfig('analyst')->getCommand());
        self::assertSame(300, $vo->getRoleConfig('developer')->getTimeout());
    }

    #[Test]
    public function getRoleConfigReturnsNullForUnknownRole(): void
    {
        $vo = $this->createStaticShared();
        self::assertNull($vo->getRoleConfig('nonexistent'));
    }

    #[Test]
    public function getRolesReturnsEmptyArrayByDefault(): void
    {
        $vo = $this->createStaticShared();
        self::assertSame([], $vo->getRoles());
    }

    private function createStaticShared(): SharedChainDefinitionVo
    {
        return new SharedChainDefinitionVo(
            name: 'test-chain',
            description: 'Test description',
            type: ChainTypeEnum::staticType,
            budget: null,
            timeout: null,
            maxTime: null,
            roles: [],
        );
    }

    private function createDynamicShared(): SharedChainDefinitionVo
    {
        return new SharedChainDefinitionVo(
            name: 'dynamic-chain',
            description: 'Dynamic description',
            type: ChainTypeEnum::dynamicType,
            budget: null,
            timeout: null,
            maxTime: null,
            roles: [],
        );
    }
}
