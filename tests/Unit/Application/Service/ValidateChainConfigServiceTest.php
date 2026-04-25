<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\ValidateChainConfigService;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\ChainNotFoundException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\ChainDefinitionValidator;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;

#[CoversClass(ValidateChainConfigService::class)]
final class ValidateChainConfigServiceTest extends TestCase
{
    private ChainLoaderInterface&MockObject $chainLoader;
    private ChainDefinitionValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->chainLoader = $this->createMock(ChainLoaderInterface::class);
        $this->validator = new ChainDefinitionValidator();
    }

    // ─── validateAll: valid config → isValid=true ──

    #[Test]
    public function validateAllWithValidConfigReturnsValid(): void
    {
        $chains = [
            'implement' => $this->createStaticChain('implement'),
            'analyze' => $this->createStaticChain('analyze'),
        ];

        $this->chainLoader->method('list')->willReturn($chains);

        $validator = $this->createValidator();
        $result = $validator->validateAll();

        self::assertTrue($result->isValid);
        self::assertSame(['implement', 'analyze'], $result->validatedChains);
        self::assertEmpty($result->errors);
    }

    // ─── validateAll: empty chains → isValid=false ──

    #[Test]
    public function validateAllWithNoChainsReturnsInvalid(): void
    {
        $this->chainLoader->method('list')->willReturn([]);

        $validator = $this->createValidator();
        $result = $validator->validateAll();

        self::assertFalse($result->isValid);
        self::assertCount(1, $result->errors);
        self::assertSame('__global__', $result->errors[0]->chainName);
        self::assertStringContainsString('No chains defined', $result->errors[0]->message);
    }

    // ─── validateAll: loader throws → isValid=false with error ──

    #[Test]
    public function validateAllWhenLoaderFailsReturnsInvalid(): void
    {
        $this->chainLoader->method('list')->willThrowException(new \RuntimeException('YAML parse error'));

        $validator = $this->createValidator();
        $result = $validator->validateAll();

        self::assertFalse($result->isValid);
        self::assertCount(1, $result->errors);
        self::assertSame('__global__', $result->errors[0]->chainName);
        self::assertStringContainsString('Failed to load', $result->errors[0]->message);
    }

    // ─── validateAll: valid dynamic chain → isValid=true ──

    #[Test]
    public function validateAllWithValidDynamicChainReturnsValid(): void
    {
        $chains = [
            'brainstorm' => $this->createDynamicChain('brainstorm'),
        ];

        $this->chainLoader->method('list')->willReturn($chains);

        $validator = $this->createValidator();
        $result = $validator->validateAll();

        self::assertTrue($result->isValid);
        self::assertSame(['brainstorm'], $result->validatedChains);
    }

    // ─── validateChain: valid specific chain → isValid=true ──

    #[Test]
    public function validateChainWithValidChainReturnsValid(): void
    {
        $this->chainLoader->method('load')->willReturn($this->createStaticChain('implement'));

        $validator = $this->createValidator();
        $result = $validator->validateChain('implement');

        self::assertTrue($result->isValid);
        self::assertSame(['implement'], $result->validatedChains);
    }

    // ─── validateChain: chain not found → isValid=false ──

    #[Test]
    public function validateChainNotFoundReturnsInvalid(): void
    {
        $this->chainLoader->method('load')->willThrowException(new ChainNotFoundException('missing'));

        $validator = $this->createValidator();
        $result = $validator->validateChain('missing');

        self::assertFalse($result->isValid);
        self::assertCount(1, $result->errors);
        self::assertSame('missing', $result->errors[0]->chainName);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────────

    private function createValidator(): ValidateChainConfigService
    {
        return new ValidateChainConfigService($this->chainLoader, $this->validator);
    }

    private function createStaticChain(string $name): ChainDefinitionVo
    {
        return ChainDefinitionVo::createFromSteps(
            name: $name,
            description: 'Test chain',
            steps: [
                ChainStepVo::agent(role: 'test_agent', runner: 'pi'),
            ],
        );
    }

    private function createDynamicChain(string $name): ChainDefinitionVo
    {
        return ChainDefinitionVo::createFromDynamic(
            name: $name,
            description: 'Test dynamic chain',
            facilitator: 'analyst',
            participants: ['dev', 'qa'],
            maxRounds: 5,
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
