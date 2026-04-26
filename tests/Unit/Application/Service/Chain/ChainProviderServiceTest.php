<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Service\Chain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainDefinitionDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Dto\ChainStepDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\ChainNotFoundException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\ChainDefinitionValidator;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ChainProviderService;

#[CoversClass(ChainProviderService::class)]
final class ChainProviderServiceTest extends TestCase
{
    private ChainLoaderInterface&MockObject $chainLoader;
    private ChainDefinitionValidator $chainValidator;
    private ChainProviderService $service;

    #[Override]
    protected function setUp(): void
    {
        $this->chainLoader = $this->createMock(ChainLoaderInterface::class);
        $this->chainValidator = new ChainDefinitionValidator();
        $this->service = new ChainProviderService($this->chainLoader, $this->chainValidator);
    }

    // ─── load: success ──

    #[Test]
    public function loadReturnsDtoFromDomainVo(): void
    {
        $chainVo = $this->createStaticChainVo('implement');
        $this->chainLoader->method('load')->with('implement')->willReturn($chainVo);

        $result = $this->service->load('implement');

        self::assertInstanceOf(ChainDefinitionDto::class, $result);
        self::assertSame('implement', $result->name);
        self::assertFalse($result->isDynamic);
        self::assertCount(1, $result->steps);
        self::assertInstanceOf(ChainStepDto::class, $result->steps[0]);
        self::assertSame('agent', $result->steps[0]->role);
        self::assertSame('pi', $result->steps[0]->runner);
        self::assertFalse($result->steps[0]->isQualityGate);
    }

    // ─── load: not found ──

    #[Test]
    public function loadPropagatesNotFoundException(): void
    {
        $this->chainLoader->method('load')
            ->willThrowException(new ChainNotFoundException('missing'));

        $this->expectException(ChainNotFoundException::class);
        $this->service->load('missing');
    }

    // ─── list: returns mapped DTOs ──

    #[Test]
    public function listReturnsMappedDtos(): void
    {
        $chains = [
            'implement' => $this->createStaticChainVo('implement'),
            'analyze' => $this->createDynamicChainVo('analyze'),
        ];

        $this->chainLoader->method('list')->willReturn($chains);

        $result = $this->service->list();

        self::assertCount(2, $result);
        self::assertArrayHasKey('implement', $result);
        self::assertArrayHasKey('analyze', $result);
        self::assertFalse($result['implement']->isDynamic);
        self::assertTrue($result['analyze']->isDynamic);
        self::assertSame('analyst', $result['analyze']->facilitator);
        self::assertSame(['dev', 'qa'], $result['analyze']->participants);
        self::assertSame(3, $result['analyze']->maxRounds);
    }

    // ─── overridePath: delegates to loader ──

    #[Test]
    public function overridePathDelegatesToLoader(): void
    {
        $this->chainLoader
            ->expects($this->once())
            ->method('overridePath')
            ->with('/custom/chains.yaml');

        $this->service->overridePath('/custom/chains.yaml');
    }

    // ─── validate: valid chain returns empty list ──

    #[Test]
    public function validateValidChainReturnsEmptyList(): void
    {
        $chainVo = $this->createStaticChainVo('valid-chain');
        $this->chainLoader->method('load')->with('valid-chain')->willReturn($chainVo);

        $dto = new ChainDefinitionDto(
            name: 'valid-chain',
            isDynamic: false,
            facilitator: null,
            participants: [],
            maxRounds: 10,
            steps: [
                new ChainStepDto(role: 'agent', runner: 'pi', label: '', isQualityGate: false),
            ],
        );

        $violations = $this->service->validate($dto);

        self::assertSame([], $violations);
    }

    // ─── validate: dynamic chain with violations ──

    #[Test]
    public function validateDynamicChainWithViolationsReturnsDtos(): void
    {
        // Создаём dynamic VO с maxRounds=0 — Domain validator найдёт нарушение
        $chainVo = ChainDefinitionVo::createFromDynamic(
            name: 'broken',
            description: 'Broken',
            facilitator: 'analyst',
            participants: ['dev'],
            maxRounds: 0,
            brainstormSystemPrompt: 'System prompt',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Fac start %s',
            facilitatorContinuePrompt: 'Fac continue %s %s %s',
            facilitatorFinalizePrompt: 'Fac finalize %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Part user %s %s',
        );

        $this->chainLoader->method('load')->with('broken')->willReturn($chainVo);

        $dto = new ChainDefinitionDto(
            name: 'broken',
            isDynamic: true,
            facilitator: 'analyst',
            participants: ['dev'],
            maxRounds: 0,
            steps: [],
        );

        $violations = $this->service->validate($dto);

        self::assertNotEmpty($violations);
        self::assertSame('broken', $violations[0]->chainName);
        self::assertSame('max_rounds', $violations[0]->field);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────────

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
