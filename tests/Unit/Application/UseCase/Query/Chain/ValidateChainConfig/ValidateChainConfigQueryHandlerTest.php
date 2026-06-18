<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\UseCase\Query\Chain\ValidateChainConfig;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Mapper\ChainConfigViolationDtoMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\ValidateChainConfig\ValidateChainConfigQuery;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\ValidateChainConfig\ValidateChainConfigQueryHandler;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\ChainNotFoundException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\InvalidFixIterationsException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainDefinitionValidatorService;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\CollectFixIterationsViolationsService;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\SharedChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;

/**
 * @see ValidateChainConfigQueryHandler
 *
 * Подход D′: при падении load() на domain-исключении конфигурации Handler достаивает
 * detailed-нарушения из carrier-исключения через коллектор (слепая зона №2).
 */
#[CoversClass(ValidateChainConfigQueryHandler::class)]
final class ValidateChainConfigQueryHandlerTest extends TestCase
{
    private ChainLoaderInterface&MockObject $chainLoader;
    private ValidateChainConfigQueryHandler $handler;

    #[Override]
    protected function setUp(): void
    {
        $this->chainLoader = $this->createMock(ChainLoaderInterface::class);
        $collector = new CollectFixIterationsViolationsService();

        $this->handler = new ValidateChainConfigQueryHandler(
            $this->chainLoader,
            new ChainDefinitionValidatorService($collector),
            new ChainConfigViolationDtoMapper(),
            $collector,
        );
    }

    // ─── Carrier-ветка: load() падает на InvalidFixIterationsException (unknown step) ──

    #[Test]
    public function loadThrowsInvalidFixIterationsWithUnknownStepReturnsDetailedViolation(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'step1'),
            ChainStepVo::createAgent(role: 'qa', name: 'step2'),
        ];
        $fixIterations = [new FixIterationGroupVo('group1', ['step1', 'ghost'], 3)];

        $this->chainLoader
            ->method('load')
            ->willThrowException(new InvalidFixIterationsException('broken', $steps, $fixIterations));

        $result = ($this->handler)(new ValidateChainConfigQuery(chainName: 'broken'));

        self::assertFalse($result->isValid);
        self::assertSame('broken', $result->validChainName);
        self::assertCount(1, $result->violations);
        $violation = $result->violations[0];
        self::assertSame('broken', $violation->chainName);
        self::assertSame('fix_iterations', $violation->field);
        // Detailed-сообщение: группа + неизвестный шаг.
        self::assertSame('fix_iteration group "group1" references unknown step "ghost".', $violation->message);
    }

    // ─── Carrier-ветка: load() падает на InvalidFixIterationsException (multiple groups) ──

    #[Test]
    public function loadThrowsInvalidFixIterationsWithDuplicateStepReturnsDetailedViolation(): void
    {
        $steps = [
            ChainStepVo::createAgent(role: 'dev', name: 'shared'),
            ChainStepVo::createAgent(role: 'qa', name: 'qa'),
            ChainStepVo::createAgent(role: 'ops', name: 'other'),
        ];
        $fixIterations = [
            new FixIterationGroupVo('groupA', ['shared', 'qa'], 3),
            new FixIterationGroupVo('groupB', ['shared', 'other'], 3),
        ];

        $this->chainLoader
            ->method('load')
            ->willThrowException(new InvalidFixIterationsException('dup', $steps, $fixIterations));

        $result = ($this->handler)(new ValidateChainConfigQuery(chainName: 'dup'));

        self::assertFalse($result->isValid);
        self::assertCount(1, $result->violations);
        // Detailed-сообщение: шаг + обе группы.
        self::assertSame(
            'fix_iteration step "shared" belongs to multiple groups ("groupA" and "groupB").',
            $result->violations[0]->message,
        );
    }

    // ─── Счастливый путь: load() возвращает валидный VO → isValid=true ──

    #[Test]
    public function validChainReturnsNoViolations(): void
    {
        $vo = StaticChainDefinitionVo::createFromSteps(
            name: 'implement',
            description: 'Test',
            steps: [
                ChainStepVo::createAgent(role: 'dev', name: 'implement'),
                ChainStepVo::createAgent(role: 'qa', name: 'review'),
            ],
            fixIterations: [new FixIterationGroupVo('dev-review', ['implement', 'review'], 3)],
        );

        $this->chainLoader->method('load')->willReturn($vo);

        $result = ($this->handler)(new ValidateChainConfigQuery(chainName: 'implement'));

        self::assertTrue($result->isValid);
        self::assertSame([], $result->violations);
        self::assertSame('implement', $result->validChainName);
    }

    // ─── load() успешен, но VO содержит step-нарушения → валидатор их репортит
    //     (carrier-ветка fix_iterations не срабатывает при успешном load()) ──

    #[Test]
    public function loadReturnsVoWithStepViolationsReturnsThem(): void
    {
        // VO со step-нарушением (agent step без role), создан через reflection в обход guard'а.
        $vo = $this->createStaticChainWithInvalidAgentStep('bad-step');

        $this->chainLoader->method('load')->willReturn($vo);

        $result = ($this->handler)(new ValidateChainConfigQuery(chainName: 'bad-step'));

        self::assertFalse($result->isValid);
        // Валидатор должен сообщить про шаг без role, а не про fix_iterations.
        $fields = array_map(static fn ($v): ?string => $v->field, $result->violations);
        self::assertContains('steps[0].role', $fields);
    }

    // ─── load() бросает ChainNotFoundException (не ChainConfigExceptionInterface)
    //     → исключение всплывает, не маскируется carrier-веткой ──

    #[Test]
    public function loadThrowsChainNotFoundExceptionPropagates(): void
    {
        $this->chainLoader
            ->method('load')
            ->willThrowException(new ChainNotFoundException('missing'));

        $this->expectException(ChainNotFoundException::class);

        ($this->handler)(new ValidateChainConfigQuery(chainName: 'missing'));
    }

    // ─── validateAllChains: list() падает на InvalidFixIterationsException
    //     → возвращаются detailed-нарушения упавшей цепочки ──

    #[Test]
    public function listThrowsInvalidFixIterationsReturnsDetailedViolation(): void
    {
        $steps = [ChainStepVo::createAgent(role: 'dev', name: 'step1')];
        $fixIterations = [new FixIterationGroupVo('group1', ['step1', 'ghost'], 3)];

        $this->chainLoader
            ->method('list')
            ->willThrowException(new InvalidFixIterationsException('broken', $steps, $fixIterations));

        $result = ($this->handler)(new ValidateChainConfigQuery(chainName: null));

        self::assertFalse($result->isValid);
        self::assertCount(1, $result->violations);
        self::assertSame(
            'fix_iteration group "group1" references unknown step "ghost".',
            $result->violations[0]->message,
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Создаёт StaticChainDefinitionVo с agent-шагом без role (в обход guard'а VO)
     * для проверки, что при успешном load() Handler делегирует валидатору.
     */
    private function createStaticChainWithInvalidAgentStep(string $name): ChainDefinitionInterface
    {
        $ref = new ReflectionClass(StaticChainDefinitionVo::class);
        /** @var StaticChainDefinitionVo $instance */
        $instance = $ref->newInstanceWithoutConstructor();

        $shared = new SharedChainDefinitionVo(
            name: $name,
            description: 'Test chain',
            type: ChainTypeEnum::staticType,
            budget: null,
            timeout: null,
            maxTime: null,
            roles: [],
        );

        $ref->getProperty('shared')->setValue($instance, $shared);

        // Agent step без role, созданный через reflection (минует guard ChainStepVo).
        $stepRef = new ReflectionClass(ChainStepVo::class);
        /** @var ChainStepVo $step */
        $step = $stepRef->newInstanceWithoutConstructor();
        $stepRef->getProperty('type')->setValue($step, \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum::agent);
        $stepRef->getProperty('role')->setValue($step, '');

        $ref->getProperty('steps')->setValue($instance, [$step]);
        $ref->getProperty('fixIterations')->setValue($instance, []);
        $ref->getProperty('defaultRetryPolicy')->setValue($instance, null);

        return $instance;
    }
}
