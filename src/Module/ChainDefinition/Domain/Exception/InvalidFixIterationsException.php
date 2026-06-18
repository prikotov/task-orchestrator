<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;

/**
 * Domain-исключение-носитель: нарушение инварианта ссылочной целостности fix-итераций.
 *
 * Выбрасывается фабрикой {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory}
 * при создании step-based цепочки, если группы fix-итераций ссылаются на неизвестные
 * шаги либо один шаг принадлежит нескольким группам. Несёт raw-входные данные фабрики
 * (имя цепочки, шаги, группы), чтобы validate-путь мог получить detailed-диагностику
 * через {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\CollectFixIterationsViolationsServiceInterface}
 * без повторного парсинга конфигурации.
 *
 * Сообщение остаётся generic (без имени группы/шага): фабрика намеренно не дублирует
 * detailed-форматирование — единственный источник подробных сообщений коллектор.
 * Run-путь остаётся fail-fast и визуально неизменным: getMessage() сохраняет прежний текст.
 *
 * Наследует \InvalidArgumentException для обратной совместимости существующих тестов
 * и обработчиков, ожидающих базовое исключение фабрики.
 */
final class InvalidFixIterationsException extends InvalidArgumentException implements ChainConfigExceptionInterface
{
    /**
     * @param string $chainName имя цепочки
     * @param list<ChainStepVo> $steps шаги цепочки (raw-вход фабрики)
     * @param list<FixIterationGroupVo> $fixIterations группы fix-итераций (raw-вход фабрики)
     * @param \Throwable|null $previous предыдущее исключение
     */
    public function __construct(
        private readonly string $chainName,
        private readonly array $steps,
        private readonly array $fixIterations,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Chain "%s": fix_iterations must reference existing named steps and each step name must belong to at most one fix_iteration group.',
                $chainName,
            ),
            0,
            $previous,
        );
    }

    public function getChainName(): string
    {
        return $this->chainName;
    }

    /**
     * @return list<ChainStepVo>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @return list<FixIterationGroupVo>
     */
    public function getFixIterations(): array
    {
        return $this->fixIterations;
    }
}
