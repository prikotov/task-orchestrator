<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\ValueObject\ChainDefinitionVo;

/**
 * Контракт загрузки цепочек оркестрации.
 */
interface ChainLoaderInterface
{
    /**
     * Загружает цепочку по имени.
     *
     * @throws \TasK\Orchestrator\Domain\Exception\NotFoundExceptionInterface если цепочка не найдена
     */
    public function load(string $name): ChainDefinitionVo;

    /**
     * Возвращает все доступные цепочки.
     *
     * @return array<string, ChainDefinitionVo>
     */
    public function list(): array;
}
