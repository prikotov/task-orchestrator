<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\DynamicLoop\Integration\Service\ChainDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\QueryBus\QueryBusComponentInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain\LoadRawChainQuery;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Integration\Service\ChainDefinition\DynamicLoopDefinitionMapperService;

#[CoversClass(DynamicLoopDefinitionMapperService::class)]
final class DynamicLoopDefinitionMapperServiceTest extends TestCase
{
    private ChainDefinitionFactory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new ChainDefinitionFactory(new FixIterationsReferenceIntegritySpecification());
    }

    #[Test]
    public function mapAppliesCommandByComplexity(): void
    {
        // Роль с базовой командой и отклонением C0: complexity 0 выбирает C0.
        $chain = $this->createDynamicChain();
        $mapper = new DynamicLoopDefinitionMapperService($this->createQueryBus($chain));

        $config = $mapper->map($chain, 0);

        self::assertSame(['--model', 'low'], $config->getRoleConfigs()['analyst']->getCommand());
    }

    #[Test]
    public function mapUsesBaseCommandForDefaultComplexity(): void
    {
        $chain = $this->createDynamicChain();
        $mapper = new DynamicLoopDefinitionMapperService($this->createQueryBus($chain));

        $config = $mapper->map($chain);

        self::assertSame(['--model', 'base'], $config->getRoleConfigs()['analyst']->getCommand());
    }

    #[Test]
    public function loadDynamicChainConfigAppliesComplexityFromArgument(): void
    {
        $chain = $this->createDynamicChain();
        $capturedQuery = null;
        $queryBus = $this->createStub(QueryBusComponentInterface::class);
        $queryBus->method('query')
            ->willReturnCallback(function (LoadRawChainQuery $query) use (&$capturedQuery, $chain): DynamicChainDefinitionVo {
                $capturedQuery = $query;

                return $chain;
            });
        $mapper = new DynamicLoopDefinitionMapperService($queryBus);

        $config = $mapper->loadDynamicChainConfig('brainstorm', 0);

        self::assertSame('brainstorm', $capturedQuery?->chainName);
        self::assertSame(['--model', 'low'], $config->getRoleConfigs()['analyst']->getCommand());
    }

    /**
     * Создаёт query bus, возвращающий цепочку при любом запросе.
     */
    private function createQueryBus(DynamicChainDefinitionVo $chain): QueryBusComponentInterface
    {
        $queryBus = $this->createStub(QueryBusComponentInterface::class);
        $queryBus->method('query')->willReturn($chain);

        return $queryBus;
    }

    private function createDynamicChain(): DynamicChainDefinitionVo
    {
        return $this->factory->createFromDynamic(
            name: 'brainstorm',
            description: 'Test dynamic chain',
            facilitator: 'analyst',
            participants: ['analyst'],
            maxRounds: 3,
            brainstormSystemPrompt: 'Base system prompt',
            facilitatorAppendPrompt: 'Facilitator append %s',
            facilitatorStartPrompt: 'Facilitator start %s',
            facilitatorContinuePrompt: 'Facilitator continue %s %s',
            facilitatorFinalizePrompt: 'Facilitator finalize %s %s',
            participantAppendPrompt: 'Participant append %s',
            participantUserPrompt: 'Participant user %s %s',
            roles: [
                'analyst' => new RoleConfigVo(
                    command: ['--model', 'base'],
                    commandsByComplexity: ['C0' => ['--model', 'low']],
                ),
            ],
        );
    }
}
