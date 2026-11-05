<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Integration\Service\ChainDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\QueryBus\QueryBusComponentInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain\LoadRawChainQuery;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\SharedChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\ChainDefinition\ChainExecutionDefinitionMapperService;

#[CoversClass(ChainExecutionDefinitionMapperService::class)]
final class ChainExecutionDefinitionMapperServiceTest extends TestCase
{
    #[Test]
    public function mapStaticChainAppliesCommandByComplexity(): void
    {
        // Роль с базовой командой и отклонением C0: complexity 0 выбирает C0,
        // сложность по умолчанию (C3) — базовую команду.
        $mapper = new ChainExecutionDefinitionMapperService($this->createQueryBus($this->createStaticChain()));

        $config = $mapper->mapStaticChain($this->createStaticChain(), 0);

        self::assertSame(['--model', 'low'], $config->getRoleConfig('analyst')?->getCommand());
    }

    #[Test]
    public function mapStaticChainUsesBaseCommandForDefaultComplexity(): void
    {
        $chain = $this->createStaticChain();
        $mapper = new ChainExecutionDefinitionMapperService($this->createQueryBus($chain));

        $config = $mapper->mapStaticChain($chain);

        self::assertSame(['--model', 'base'], $config->getRoleConfig('analyst')?->getCommand());
    }

    #[Test]
    public function loadStaticChainConfigAppliesComplexityFromArgument(): void
    {
        $chain = $this->createStaticChain();
        $capturedQuery = null;
        $queryBus = $this->createStub(QueryBusComponentInterface::class);
        $queryBus->method('query')
            ->willReturnCallback(function (LoadRawChainQuery $query) use (&$capturedQuery, $chain): StaticChainDefinitionVo {
                $capturedQuery = $query;

                return $chain;
            });
        $mapper = new ChainExecutionDefinitionMapperService($queryBus);

        $config = $mapper->loadStaticChainConfig('implement', 0);

        self::assertSame('implement', $capturedQuery?->chainName);
        self::assertSame(['--model', 'low'], $config->getRoleConfig('analyst')?->getCommand());
    }

    /**
     * Создаёт query bus, возвращающий цепочку при любом запросе.
     */
    private function createQueryBus(StaticChainDefinitionVo $chain): QueryBusComponentInterface
    {
        $queryBus = $this->createStub(QueryBusComponentInterface::class);
        $queryBus->method('query')->willReturn($chain);

        return $queryBus;
    }

    private function createStaticChain(): StaticChainDefinitionVo
    {
        return new StaticChainDefinitionVo(
            shared: new SharedChainDefinitionVo(
                name: 'implement',
                description: 'Test chain',
                type: ChainTypeEnum::staticType,
                budget: null,
                timeout: null,
                maxTime: null,
                roles: [
                    'analyst' => new RoleConfigVo(
                        command: ['--model', 'base'],
                        commandsByComplexity: ['C0' => ['--model', 'low']],
                    ),
                ],
            ),
            steps: [ChainStepVo::createAgent(role: 'analyst')],
            fixIterations: [],
        );
    }
}
