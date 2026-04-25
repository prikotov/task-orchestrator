<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * Value Object нарушения конфигурации цепочки.
 *
 * Представляет одну ошибку валидации: имя цепочки, путь к полю и описание проблемы.
 * Неизменяемый, создаётся Domain Specification при обнаружении нарушения инварианта.
 */
final readonly class ChainConfigViolationVo
{
    /**
     * @param string $chainName имя цепочки, в которой обнаружено нарушение
     * @param string|null $field путь к полю (например, 'steps[0].role') или null
     * @param string $message человекочитаемое описание нарушения
     */
    public function __construct(
        private string $chainName,
        private ?string $field,
        private string $message,
    ) {
    }

    public function getChainName(): string
    {
        return $this->chainName;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
