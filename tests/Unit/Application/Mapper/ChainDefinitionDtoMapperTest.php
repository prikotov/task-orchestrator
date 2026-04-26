<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainStepDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper\ChainDefinitionDtoMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;

#[CoversClass(ChainDefinitionDtoMapper::class)]
final class ChainDefinitionDtoMapperTest extends TestCase
{
    private ChainDefinitionDtoMapper $mapper;

    #[Override]
    protected function setUp(): void
    {
        $this->mapper = new ChainDefinitionDtoMapper();
    }

    #[Test]
    public function mapStaticChainVoToDto(): void
    {
        $chainVo = $this->createStaticChainVo('implement');

        $dto = $this->mapper->map($chainVo);

        self::assertSame('implement', $dto->name);
        self::assertFalse($dto->isDynamic);
        self::assertCount(1, $dto->steps);
        self::assertInstanceOf(ChainStepDto::class, $dto->steps[0]);
        self::assertSame('agent', $dto->steps[0]->role);
        self::assertSame('pi', $dto->steps[0]->runner);
        self::assertFalse($dto->steps[0]->isQualityGate);
    }

    #[Test]
    public function mapDynamicChainVoToDto(): void
    {
        $chainVo = $this->createDynamicChainVo('analyze');

        $dto = $this->mapper->map($chainVo);

        self::assertSame('analyze', $dto->name);
        self::assertTrue($dto->isDynamic);
        self::assertSame('analyst', $dto->facilitator);
        self::assertSame(['dev', 'qa'], $dto->participants);
        self::assertSame(3, $dto->maxRounds);
    }

    #[Test]
    public function mapListReturnsKeyedList(): void
    {
        $chains = [
            'implement' => $this->createStaticChainVo('implement'),
            'analyze' => $this->createDynamicChainVo('analyze'),
        ];

        $result = $this->mapper->mapList($chains);

        self::assertCount(2, $result);
        self::assertArrayHasKey('implement', $result);
        self::assertArrayHasKey('analyze', $result);
        self::assertFalse($result['implement']->isDynamic);
        self::assertTrue($result['analyze']->isDynamic);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function createStaticChainVo(string $name = 'test-static'): ChainDefinitionVo
    {
        return ChainDefinitionVo::createFromSteps(
            name: $name,
            description: 'Test static chain',
            steps: [
                ChainStepVo::agent(role: 'agent', runner: 'pi'),
            ],
        );
    }

    private function createDynamicChainVo(string $name = 'test-dynamic'): ChainDefinitionVo
    {
        return ChainDefinitionVo::createFromDynamic(
            name: $name,
            description: 'Test dynamic chain',
            facilitator: 'analyst',
            participants: ['dev', 'qa'],
            maxRounds: 3,
            brainstormSystemPrompt: 'System prompt',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Fac start %s',
            facilitatorContinuePrompt: 'Fac continue %s %s %s',
            facilitatorFinalizePrompt: 'Fac finalize %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Part user %s %s',
        );
    }
}
