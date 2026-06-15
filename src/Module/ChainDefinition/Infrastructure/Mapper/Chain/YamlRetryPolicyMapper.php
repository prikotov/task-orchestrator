<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;

/**
 * Маппер retry-политики из raw YAML-секции в {@see ChainRetryPolicyVo}.
 *
 * Техническая граница на границе Infrastructure ↔ Domain: изолирует обработку
 * null/пустого массива и делегирует сборку VO в {@see ChainRetryPolicyVo::createFromArray()}.
 * Бизнес-логики не содержит.
 *
 * @see docs/conventions/core_patterns/mapper.md
 */
final readonly class YamlRetryPolicyMapper
{
    /**
     * Маппит raw YAML-секцию retry_policy в VO.
     *
     * Семантика сохранена byte-to-byte относительно предыдущего helper-контракта:
     * `null` и пустой массив возвращают null; непустой массив собирается в VO.
     *
     * @param array{max_retries?: int, initial_delay_ms?: int, max_delay_ms?: int, multiplier?: float}|null $raw
     */
    public function mapToChainRetryPolicy(?array $raw): ?ChainRetryPolicyVo
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        return ChainRetryPolicyVo::createFromArray($raw);
    }
}
