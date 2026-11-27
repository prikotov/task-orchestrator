<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Helper;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRunResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopTurnResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;

/**
 * Helper преобразования результата turn'а dynamic-цикла в отчётные VO.
 *
 * Вынесено из
 * {@see \TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\ExecuteDynamicTurnService}:
 * исполнителю turn'ов остаётся оркестрация шагов, а техническое преобразование
 * результата агента — здесь.
 *
 * Helper по docs/conventions/core-patterns/helper.md: только статические pure-функции —
 * без состояния, DI и I/O. Static-контракт санкционирован ignorepattern StaticAccess
 * в phpmd.xml (fromTurnResult/normalizeEmptyOutput — чистые фабрики и нормализация).
 */
final class DynamicRoundResultHelper
{
    /** @var string Ошибка пустого ответа агента */
    private const string EMPTY_OUTPUT_ERROR_MESSAGE = 'Agent returned empty output.';

    /**
     * Собирает отчётный VO раунда из результата turn'а (маппинг полей результата
     * агента в {@see DynamicRoundResultVo} для журнала и метрик).
     *
     * @param bool $isFacilitator признак раунда фасилитатора (для отчётности)
     * @param string|null $thinking уровень thinking, разобранный из команды роли
     */
    public static function fromTurnResult(
        DynamicLoopTurnResultVo $turn,
        int $step,
        string $role,
        bool $isFacilitator,
        ?string $thinking = null,
    ): DynamicRoundResultVo {
        $agent = $turn->getAgentResult();

        return new DynamicRoundResultVo(
            round: $step,
            role: $role,
            isFacilitator: $isFacilitator,
            outputText: $agent->getOutputText(),
            inputTokens: $agent->getInputTokens(),
            outputTokens: $agent->getOutputTokens(),
            cost: $agent->getCost(),
            duration: $turn->getDuration(),
            isError: $agent->isError(),
            errorMessage: $agent->getErrorMessage(),
            invocation: $turn->getInvocation(),
            systemPrompt: $turn->getSystemPrompt(),
            userPrompt: $turn->getUserPrompt(),
            timedOut: $agent->isTimedOut(),
            model: $agent->getModel(),
            provider: $agent->getProvider(),
            thinking: $thinking,
            cacheReadTokens: $agent->getCacheReadTokens(),
            cacheWriteTokens: $agent->getCacheWriteTokens(),
        );
    }

    /**
     * Нормализует «успешный» ответ без текста: пустой output заменяется на
     * ошибку EMPTY_OUTPUT_ERROR_MESSAGE, чтобы пустой ответ агента не попадал
     * в журнал и метрики как содержательный результат.
     */
    public static function normalizeEmptyOutput(DynamicLoopTurnResultVo $turnResult): DynamicLoopTurnResultVo
    {
        if ($turnResult->getAgentResult()->isError() || trim($turnResult->getAgentResult()->getOutputText()) !== '') {
            return $turnResult;
        }

        return new DynamicLoopTurnResultVo(
            agentResult: DynamicLoopRunResultVo::createError(
                errorMessage: self::EMPTY_OUTPUT_ERROR_MESSAGE,
                exitCode: 0,
            ),
            duration: $turnResult->getDuration(),
            userPrompt: $turnResult->getUserPrompt(),
            systemPrompt: $turnResult->getSystemPrompt(),
            invocation: $turnResult->getInvocation(),
        );
    }
}
